<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Marketplace\Models\VendorAttendance;
use App\Modules\Marketplace\Models\VendorOffer;
use App\Modules\Marketplace\Services\MatchingSettings;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorOfferController extends Controller
{
    public function mine(Request $request, AutoMatchAction $match, SettlePaymentAction $settle): JsonResponse
    {
        $match->sweepAndRefill();
        $settle->releaseDuePayouts();

        $offers = VendorOffer::query()
            ->where('caterer_user_id', $request->user()->id)
            ->with([
                'serviceRequest.eventDetail.shifts.category',
                'serviceRequest.taskDetail',
                'serviceRequest.requester',
                'serviceRequest.vendorAttendance',
                'serviceRequest.payouts',
                'serviceRequest.assignments' => fn ($q) => $q->whereIn('status', Assignment::COMMITTED),
            ])
            ->latest()
            ->get()
            ->each(fn (VendorOffer $offer) => $this->hideAttendanceOtps($offer));

        return response()->json($offers);
    }

    public function show(Request $request, string $offer, SettlePaymentAction $settle): JsonResponse
    {
        $offer = $this->ownedOffer($request, $offer);
        $settle->releaseDuePayouts();

        $offer->load([
            'serviceRequest.eventDetail.shifts.category',
            'serviceRequest.taskDetail',
            'serviceRequest.requester',
            'serviceRequest.vendorAttendance',
            'serviceRequest.payouts',
            'serviceRequest.assignments' => fn ($q) => $q->whereIn('status', Assignment::COMMITTED)->with('worker'),
        ]);
        $this->hideAttendanceOtps($offer);

        return response()->json($offer);
    }

    public function accept(Request $request, string $offer, Auditor $auditor, AutoMatchAction $match): JsonResponse
    {
        $offer = $this->ownedOffer($request, $offer);

        $profile = $request->user()->catererProfile;
        if (! $profile?->isActive()) {
            throw ValidationException::withMessages(['offer' => 'Company profile must be active first.']);
        }

        $fresh = DB::transaction(function () use ($offer, $match, $request) {
            $siblings = VendorOffer::query()
                ->where('service_request_id', $offer->service_request_id)
                ->lockForUpdate()
                ->get();

            $row = $siblings->firstWhere('id', $offer->id) ?? VendorOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $booking = $row->serviceRequest()->lockForUpdate()->first();
            $match->expireStaleVendorOffers($booking);

            $row->refresh();
            $booking?->refresh();

            if ($row->status !== 'invited' || ! MatchingSettings::canAcceptOffer($row->expires_at)) {
                if ($row->status === 'invited') {
                    $row->update(['status' => 'expired', 'responded_at' => now()]);
                }
                throw ValidationException::withMessages(['offer' => 'This offer expired. Another caterer may have taken it.']);
            }

            if ($booking?->vendor_user_id) {
                $row->update(['status' => 'expired', 'responded_at' => now()]);
                throw ValidationException::withMessages(['offer' => 'Another catering company already accepted this job.']);
            }

            $row->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $siblings->where('id', '!=', $row->id)->where('status', 'invited')->each(function (VendorOffer $other) {
                $other->update(['status' => 'expired', 'responded_at' => now()]);
            });

            $booking->update(['vendor_user_id' => $request->user()->id]);
            $match->closeAllOpenWorkerOffers($booking);

            if (! in_array($booking->status, ['confirmed', 'in_progress', 'completed'], true)) {
                $booking->transitionTo('confirmed', $request->user(), 'Catering company accepted the package');
            }

            $this->mintAttendance($booking, $request->user()->id);

            return $row->fresh();
        });

        $auditor->record($request->user(), 'vendor_offer.accepted', $fresh);

        $fresh->load([
            'serviceRequest.eventDetail.shifts.category',
            'serviceRequest.taskDetail',
            'serviceRequest.requester',
            'serviceRequest.vendorAttendance',
            'serviceRequest.payouts',
        ]);
        $this->hideAttendanceOtps($fresh);

        return response()->json($fresh);
    }

    public function checkIn(Request $request, string $offer, Auditor $auditor): JsonResponse
    {
        $offer = $this->ownedOffer($request, $offer);
        $data = $request->validate(['otp' => ['required', 'string']]);
        $booking = $offer->serviceRequest;
        $attendance = $booking?->vendorAttendance;

        if ($offer->status !== 'accepted' || (int) $booking?->vendor_user_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['otp' => 'Accept this package before check-in.']);
        }

        if (! $attendance || $attendance->check_in_otp !== $data['otp']) {
            throw ValidationException::withMessages(['otp' => 'Wrong check-in OTP. Ask the client for the code on their booking.']);
        }

        if ($attendance->check_in_at) {
            throw ValidationException::withMessages(['otp' => 'Already checked in.']);
        }

        $attendance->update(['check_in_at' => now()]);
        if ($booking->status !== 'in_progress') {
            $booking->transitionTo('in_progress', $request->user(), 'Catering company checked in at venue');
        }
        $auditor->record($request->user(), 'vendor.checked_in', $offer);

        $offer->load(['serviceRequest.vendorAttendance', 'serviceRequest.payouts', 'serviceRequest.eventDetail', 'serviceRequest.taskDetail', 'serviceRequest.requester']);
        $this->hideAttendanceOtps($offer);

        return response()->json($offer);
    }

    public function checkOut(Request $request, string $offer, Auditor $auditor, SettlePaymentAction $settle): JsonResponse
    {
        $offer = $this->ownedOffer($request, $offer);
        $data = $request->validate(['otp' => ['required', 'string']]);
        $booking = $offer->serviceRequest;
        $attendance = $booking?->vendorAttendance;

        if ($offer->status !== 'accepted' || (int) $booking?->vendor_user_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['otp' => 'This package is not yours.']);
        }

        if (! $attendance?->check_in_at) {
            throw ValidationException::withMessages(['otp' => 'Check in first.']);
        }

        if (! $attendance || $attendance->check_out_otp !== $data['otp']) {
            throw ValidationException::withMessages(['otp' => 'Wrong check-out OTP. Ask the client for the code on their booking.']);
        }

        if ($attendance->check_out_at) {
            throw ValidationException::withMessages(['otp' => 'Already checked out.']);
        }

        $attendance->update(['check_out_at' => now()]);
        $settle->createVendorPayout($booking);
        if (! in_array($booking->status, ['completed', 'settled'], true)) {
            $booking->transitionTo('completed', $request->user(), 'Catering company checked out');
        }
        $auditor->record($request->user(), 'vendor.checked_out', $offer);

        $offer->load(['serviceRequest.vendorAttendance', 'serviceRequest.payouts', 'serviceRequest.eventDetail', 'serviceRequest.taskDetail', 'serviceRequest.requester']);
        $this->hideAttendanceOtps($offer);

        return response()->json($offer);
    }

    public function decline(Request $request, string $offer, Auditor $auditor, AutoMatchAction $match): JsonResponse
    {
        $offer = $this->ownedOffer($request, $offer);

        $offer->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
        $auditor->record($request->user(), 'vendor_offer.declined', $offer);
        $match->handle($offer->serviceRequest, $request->user());

        return response()->json($offer->fresh());
    }

    private function ownedOffer(Request $request, string $key): VendorOffer
    {
        $query = VendorOffer::query()->where('caterer_user_id', $request->user()->id);
        $offer = ctype_digit($key)
            ? $query->where('id', $key)->first()
            : $query->whereHas('serviceRequest', fn ($q) => $q->where('slug', $key))->first();

        abort_unless($offer, 404);

        return $offer;
    }

    private function mintAttendance($booking, int $vendorId): void
    {
        $in = (string) random_int(1000, 9999);
        do {
            $out = (string) random_int(1000, 9999);
        } while ($out === $in);

        VendorAttendance::query()->firstOrCreate(
            ['service_request_id' => $booking->id],
            [
                'vendor_user_id' => $vendorId,
                'check_in_otp' => $in,
                'check_out_otp' => $out,
            ]
        );
    }

    private function hideAttendanceOtps(VendorOffer $offer): void
    {
        $offer->serviceRequest?->vendorAttendance?->makeHidden(['check_in_otp', 'check_out_otp']);
        $offer->serviceRequest?->presentAttendance(false);
    }
}
