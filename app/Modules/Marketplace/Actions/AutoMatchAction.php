<?php

namespace App\Modules\Marketplace\Actions;

use App\Models\User;
use App\Modules\Events\Models\EventShift;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Marketplace\Models\CatererProfile;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Marketplace\Models\VendorOffer;
use App\Modules\Marketplace\Services\MatchingSettings;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Ops\Services\Notifier;
use App\Modules\Workers\Models\WorkerProfile;
use Illuminate\Support\Collection;

class AutoMatchAction
{
    public const OFFER_SECONDS = 90;

    /** @deprecated Use MatchingSettings::vendorOfferSeconds() */
    public const VENDOR_OFFER_SECONDS = 180;

    public function __construct(
        private Auditor $auditor,
        private Notifier $notifier,
    ) {}

    public function handle(ServiceRequest $request, User $actor, ?int $ringSeconds = null): ServiceRequest
    {
        $request->load(['eventDetail.shifts.category', 'taskDetail', 'assignments']);
        $this->expireStale($request);
        $this->expireStaleVendorOffers($request);

        if ($request->vendor_user_id) {
            $this->confirmIfFilled($request, $actor);

            return $request->fresh(['eventDetail.shifts.assignments.worker', 'taskDetail', 'assignments.worker', 'payments', 'vendor.catererProfile']);
        }

        if ($this->allSlotsFilled($request)) {
            $this->expireOpenVendorOffers($request);
            $this->confirmIfFilled($request, $actor);

            return $request->fresh(['eventDetail.shifts.assignments.worker', 'taskDetail', 'assignments.worker', 'payments', 'vendor.catererProfile']);
        }

        $this->ringCaterers($request, $actor, $ringSeconds);
        $this->confirmIfFilled($request, $actor);

        return $request->fresh(['eventDetail.shifts.assignments.worker', 'taskDetail', 'assignments.worker', 'payments', 'vendor.catererProfile']);
    }

    public function refill(Assignment $old, User $actor): void
    {
        $request = $old->serviceRequest()->with(['eventDetail.shifts', 'taskDetail'])->first();
        if (! $request) {
            return;
        }

        $this->expireStale($request);
        $this->expireStaleVendorOffers($request);

        if ($request->vendor_user_id) {
            $this->confirmIfFilled($request, $actor);

            return;
        }

        $this->ringCaterers($request, $actor, null);
        $this->confirmIfFilled($request, $actor);
    }

    public function expireStale(?ServiceRequest $request = null): int
    {
        $query = Assignment::query()
            ->where('status', 'invited')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($request) {
            $query->where('service_request_id', $request->id);
        }

        return $query->update([
            'status' => 'expired',
            'responded_at' => now(),
        ]);
    }

    public function sweepAndRefill(): void
    {
        $this->closeUnfilledBookings();
        $this->expireStale();
        $this->expireStaleVendorOffers();

        $ids = Assignment::query()
            ->where('status', 'invited')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('service_request_id')
            ->unique();

        foreach (ServiceRequest::query()->whereIn('id', $ids)->with('requester')->get() as $req) {
            if ($req->requester) {
                $this->handle($req, $req->requester);
            }
        }
    }

    public function closeUnfilledBookings(): void
    {
        $bookings = ServiceRequest::query()
            ->whereNull('vendor_user_id')
            ->whereIn('status', ['matching', 'filling'])
            ->whereHas('payments', fn ($q) => $q->where('status', 'paid'))
            ->whereNotNull('scheduled_start')
            ->with('requester')
            ->get()
            ->filter(fn (ServiceRequest $request) => MatchingSettings::acceptDeadline($request->scheduled_start)->isPast());

        foreach ($bookings as $request) {
            $this->expireOpenVendorOffers($request);
            if ($request->requester && $request->status !== 'unmatched') {
                $request->transitionTo('unmatched', $request->requester, 'No catering company accepted before the job day ended');
            }
        }
    }

    public function expireStaleVendorOffers(?ServiceRequest $request = null): int
    {
        $query = VendorOffer::query()
            ->where('status', 'invited')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($request) {
            $query->where('service_request_id', $request->id);
        }

        return $query->update([
            'status' => 'expired',
            'responded_at' => now(),
        ]);
    }

    public function expireOpenVendorOffers(ServiceRequest $request): int
    {
        return VendorOffer::query()
            ->where('service_request_id', $request->id)
            ->where('status', 'invited')
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);
    }

    public function closeAllOpenWorkerOffers(ServiceRequest $request): void
    {
        Assignment::query()
            ->where('service_request_id', $request->id)
            ->where('status', 'invited')
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);
    }

    public function closeOpenOffers(ServiceRequest $request, ?int $shiftId): void
    {
        Assignment::query()
            ->where('service_request_id', $request->id)
            ->when(
                $shiftId,
                fn ($q) => $q->where('event_shift_id', $shiftId),
                fn ($q) => $q->whereNull('event_shift_id')
            )
            ->where('status', 'invited')
            ->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);
    }

    private function ringShift(ServiceRequest $request, EventShift $shift, User $actor): void
    {
        $needed = $shift->openSlots();
        if ($needed < 1) {
            $this->closeOpenOffers($request, $shift->id);

            return;
        }

        $ringing = $this->openOfferCount($request, $shift->id);
        $want = $this->waveSize($needed);
        $toRing = max(0, $want - $ringing);
        if ($toRing < 1) {
            return;
        }

        foreach ($this->rankedWorkers($request, $shift->category_id, $shift->id)->take($toRing) as $profile) {
            $this->offer($request, $profile, $actor, $shift->id, $shift->eventDetail?->title ?? 'Event shift');
        }
    }

    private function ringTask(ServiceRequest $request, User $actor): void
    {
        $committed = $this->committedCount($request, null);
        $needed = max(0, $request->required_workers - $committed);
        if ($needed < 1) {
            $this->closeOpenOffers($request, null);

            return;
        }

        $ringing = $this->openOfferCount($request, null);
        $toRing = max(0, $this->waveSize($needed) - $ringing);
        if ($toRing < 1) {
            return;
        }

        foreach ($this->rankedWorkers($request, null, null)->take($toRing) as $profile) {
            $this->offer($request, $profile, $actor, null, $request->taskDetail?->title ?? 'Task');
        }
    }

    private function waveSize(int $needed): int
    {
        return max($needed * 3, $needed + 2);
    }

    private function committedCount(ServiceRequest $request, ?int $shiftId): int
    {
        return Assignment::query()
            ->where('service_request_id', $request->id)
            ->when($shiftId, fn ($q) => $q->where('event_shift_id', $shiftId), fn ($q) => $q->whereNull('event_shift_id'))
            ->whereIn('status', Assignment::COMMITTED)
            ->count();
    }

    private function openOfferCount(ServiceRequest $request, ?int $shiftId): int
    {
        return Assignment::query()
            ->where('service_request_id', $request->id)
            ->when($shiftId, fn ($q) => $q->where('event_shift_id', $shiftId), fn ($q) => $q->whereNull('event_shift_id'))
            ->where('status', 'invited')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    private function ringCaterers(ServiceRequest $request, User $actor, ?int $ringSeconds = null): void
    {
        if ($request->vendor_user_id) {
            return;
        }

        $seconds = MatchingSettings::vendorOfferSeconds($ringSeconds);
        $acceptUntil = MatchingSettings::acceptDeadline($request->scheduled_start);

        $open = VendorOffer::query()
            ->where('service_request_id', $request->id)
            ->where('status', 'invited')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($open > 0) {
            return;
        }

        $already = VendorOffer::query()
            ->where('service_request_id', $request->id)
            ->whereIn('status', ['invited', 'accepted', 'declined'])
            ->pluck('caterer_user_id');

        $categoryIds = $this->requestedCategoryIds($request);
        $city = mb_strtolower((string) $request->city);

        $profiles = CatererProfile::query()
            ->where('status', 'active')
            ->where('is_available', true)
            ->whereNotIn('user_id', $already)
            ->when($city !== '', fn ($q) => $q->whereRaw('lower(city) = ?', [$city]))
            ->when($categoryIds !== [], fn ($q) => $q->whereHas('skills', fn ($s) => $s->whereIn('category_id', $categoryIds)))
            ->with('user')
            ->get();

        $title = $request->eventDetail?->title ?? $request->taskDetail?->title ?? 'Job';

        foreach ($profiles as $profile) {
            $offer = VendorOffer::query()->create([
                'service_request_id' => $request->id,
                'caterer_user_id' => $profile->user_id,
                'status' => 'invited',
                'assigned_by' => $actor->id,
                'invited_at' => now(),
                'urgent_until' => now()->addSeconds($seconds),
                'expires_at' => $acceptUntil,
            ]);

            if (in_array($request->status, ['matching', 'awaiting_payment', 'posted', 'draft'], true)) {
                $request->transitionTo('matching', $actor, 'Ringing nearby catering companies');
            }

            $this->notifier->send($profile->user, 'vendor.ringing', [
                'offer_id' => $offer->id,
                'title' => $title,
                'urgent_until' => $offer->urgent_until?->toIso8601String(),
                'expires_at' => $offer->expires_at?->toIso8601String(),
            ]);
            $this->auditor->record($actor, 'vendor_offer.rung', $offer);
        }
    }

    private function requestedCategoryIds(ServiceRequest $request): array
    {
        if ($request->type === 'event') {
            return $request->eventDetail?->shifts?->pluck('category_id')->filter()->unique()->values()->all() ?? [];
        }

        return [];
    }

    private function allSlotsFilled(ServiceRequest $request): bool
    {
        if ($request->type === 'task') {
            return $this->committedCount($request, null) >= $request->required_workers;
        }

        $request->loadMissing('eventDetail.shifts.assignments');
        $shifts = $request->eventDetail?->shifts ?? collect();

        return $shifts->isNotEmpty() && $shifts->every(fn (EventShift $shift) => $shift->openSlots() === 0);
    }

    private function rankedWorkers(ServiceRequest $request, ?int $categoryId, ?int $shiftId): Collection
    {
        $busyOnThis = Assignment::query()
            ->when($shiftId, fn ($q) => $q->where('event_shift_id', $shiftId), fn ($q) => $q->where('service_request_id', $request->id))
            ->whereNotIn('status', ['expired'])
            ->pluck('worker_user_id');

        $overlapping = Assignment::query()
            ->whereIn('status', array_merge(Assignment::COMMITTED, ['invited']))
            ->whereHas('serviceRequest', function ($q) use ($request) {
                $q->where('id', '!=', $request->id)
                    ->where('scheduled_start', '<', $request->scheduled_end)
                    ->where('scheduled_end', '>', $request->scheduled_start);
            })
            ->pluck('worker_user_id');

        $exclude = $busyOnThis->merge($overlapping)->unique();

        return WorkerProfile::query()
            ->where('status', 'active')
            ->where('is_available', true)
            ->whereNotIn('user_id', $exclude)
            ->when($categoryId, fn ($q) => $q->whereHas('skills', fn ($s) => $s->where('category_id', $categoryId)))
            ->with('user')
            ->get()
            ->sortByDesc(fn (WorkerProfile $p) => ($p->rating_avg * 20) + $p->reliability_score + 12)
            ->values();
    }

    private function offer(ServiceRequest $request, WorkerProfile $profile, User $actor, ?int $shiftId, string $title): Assignment
    {
        $assignment = Assignment::query()->create([
            'service_request_id' => $request->id,
            'event_shift_id' => $shiftId,
            'worker_user_id' => $profile->user_id,
            'status' => 'invited',
            'assigned_by' => $actor->id,
            'invited_at' => now(),
            'expires_at' => now()->addSeconds(self::OFFER_SECONDS),
        ]);

        if (in_array($request->status, ['matching', 'awaiting_payment', 'posted', 'draft'], true)) {
            $request->transitionTo('matching', $actor, 'Ringing nearby workers');
        }

        $this->notifier->send($profile->user, 'shift.ringing', [
            'assignment_id' => $assignment->id,
            'title' => $title,
            'expires_at' => $assignment->expires_at?->toIso8601String(),
        ]);
        $this->auditor->record($actor, 'assignment.rung', $assignment);

        return $assignment;
    }

    public function confirmIfFilled(ServiceRequest $request, User $actor): void
    {
        $request->refresh();
        $paid = $request->payments()->where('status', 'paid')->exists();
        if (! $paid) {
            return;
        }

        if ($request->type === 'task') {
            $filled = $this->committedCount($request, null);
            if ($filled >= $request->required_workers && ! in_array($request->status, ['confirmed', 'in_progress', 'completed'], true)) {
                $this->closeOpenOffers($request, null);
                $this->expireOpenVendorOffers($request);
                $request->transitionTo('confirmed', $actor, 'Workers accepted the task');
            } elseif ($filled > 0 && $request->status === 'matching') {
                $request->transitionTo('filling', $actor, 'First worker accepted');
            }

            return;
        }

        $request->load('eventDetail.shifts.assignments');
        $shifts = $request->eventDetail?->shifts ?? collect();
        $allFilled = $shifts->isNotEmpty() && $shifts->every(fn (EventShift $shift) => $shift->openSlots() === 0);
        $anyAccepted = $shifts->contains(fn (EventShift $shift) => $shift->filledCount() > 0);

        if ($allFilled && ! in_array($request->status, ['confirmed', 'in_progress', 'completed'], true)) {
            foreach ($shifts as $shift) {
                $this->closeOpenOffers($request, $shift->id);
            }
            $this->expireOpenVendorOffers($request);
            $request->transitionTo('confirmed', $actor, 'Workers accepted the event');
        } elseif ($anyAccepted && $request->status === 'matching') {
            $request->transitionTo('filling', $actor, 'First worker accepted');
        }
    }
}
