<?php

namespace App\Modules\Marketplace\Actions;

use App\Models\User;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Ops\Services\Notifier;
use Illuminate\Validation\ValidationException;

class AssignToRequestAction
{
    public function __construct(
        private Auditor $auditor,
        private Notifier $notifier,
    ) {}

    public function handle(ServiceRequest $request, User $worker, User $actor, ?int $categoryId = null): Assignment
    {
        $profile = $worker->workerProfile;
        if (! $profile || ! $profile->isActive()) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker KYC is not approved.',
            ]);
        }

        if ($categoryId && ! $profile->skills()->where('category_id', $categoryId)->exists()) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker does not have this skill.',
            ]);
        }

        $active = $request->assignments()
            ->whereIn('status', Assignment::COMMITTED)
            ->count();

        if ($active >= $request->required_workers) {
            throw ValidationException::withMessages([
                'request' => 'This request is already filled.',
            ]);
        }

        $already = $request->assignments()
            ->where('worker_user_id', $worker->id)
            ->whereNotIn('status', ['declined', 'cancelled', 'replaced', 'expired'])
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'worker_user_id' => 'Worker is already assigned.',
            ]);
        }

        $assignment = Assignment::query()->create([
            'service_request_id' => $request->id,
            'event_shift_id' => null,
            'worker_user_id' => $worker->id,
            'status' => 'invited',
            'assigned_by' => $actor->id,
            'invited_at' => now(),
            'expires_at' => now()->addSeconds(AutoMatchAction::OFFER_SECONDS),
        ]);

        if (in_array($request->status, ['matching', 'awaiting_payment', 'posted'], true)) {
            $request->transitionTo('filling', $actor, 'Worker assigned');
        }

        $this->notifier->send($worker, 'task.invited', [
            'assignment_id' => $assignment->id,
            'title' => $request->taskDetail?->title ?? 'Task',
        ]);
        $this->auditor->record($actor, 'assignment.created', $assignment);

        return $assignment->load(['worker', 'serviceRequest.taskDetail']);
    }
}
