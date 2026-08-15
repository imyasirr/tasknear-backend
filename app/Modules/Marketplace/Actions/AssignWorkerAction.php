<?php

namespace App\Modules\Marketplace\Actions;

use App\Models\User;
use App\Modules\Events\Models\EventShift;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Ops\Services\Notifier;
use Illuminate\Validation\ValidationException;

class AssignWorkerAction
{
    public function __construct(
        private Auditor $auditor,
        private Notifier $notifier,
    ) {}

    public function handle(EventShift $shift, User $worker, User $actor): Assignment
    {
        $profile = $worker->workerProfile;

        if (! $profile || ! $profile->isActive()) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker KYC is not approved.',
            ]);
        }

        $hasSkill = $profile->skills()->where('category_id', $shift->category_id)->exists();
        if (! $hasSkill) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker does not have this skill.',
            ]);
        }

        if ($shift->openSlots() < 1) {
            throw ValidationException::withMessages([
                'shift' => 'This shift is already filled.',
            ]);
        }

        $already = Assignment::query()
            ->where('event_shift_id', $shift->id)
            ->where('worker_user_id', $worker->id)
            ->whereNotIn('status', ['declined', 'cancelled', 'replaced', 'expired'])
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker is already assigned to this shift.',
            ]);
        }

        $request = $shift->eventDetail->serviceRequest;

        $assignment = Assignment::query()->create([
            'service_request_id' => $request->id,
            'event_shift_id' => $shift->id,
            'worker_user_id' => $worker->id,
            'status' => 'invited',
            'assigned_by' => $actor->id,
            'invited_at' => now(),
            'expires_at' => now()->addSeconds(AutoMatchAction::OFFER_SECONDS),
        ]);

        if ($request->status === 'matching' || $request->status === 'awaiting_payment') {
            $request->transitionTo('filling', $actor, 'First worker assigned');
        }

        $this->notifier->send($worker, 'shift.invited', [
            'assignment_id' => $assignment->id,
            'title' => $shift->eventDetail->title,
        ]);
        $this->auditor->record($actor, 'assignment.created', $assignment);

        return $assignment->load(['worker', 'shift.category']);
    }
}
