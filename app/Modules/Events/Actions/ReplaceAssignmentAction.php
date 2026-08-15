<?php

namespace App\Modules\Events\Actions;

use App\Models\User;
use App\Modules\Events\Models\Replacement;
use App\Modules\Marketplace\Actions\AssignWorkerAction;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplaceAssignmentAction
{
    public function __construct(
        private AssignWorkerAction $assignWorker,
        private Auditor $auditor,
    ) {}

    public function handle(Assignment $old, User $replacement, User $actor, ?string $reason = null): Assignment
    {
        return DB::transaction(function () use ($old, $replacement, $actor, $reason) {
            $old->update(['status' => 'replaced', 'responded_at' => now()]);

            if (! $old->event_shift_id) {
                $new = app(\App\Modules\Marketplace\Actions\AssignToRequestAction::class)
                    ->handle($old->serviceRequest, $replacement, $actor);

                Replacement::query()->create([
                    'old_assignment_id' => $old->id,
                    'new_assignment_id' => $new->id,
                    'reason' => $reason,
                    'created_by' => $actor->id,
                ]);
                $this->auditor->record($actor, 'assignment.replaced', $old, [
                    'new_assignment_id' => $new->id,
                    'reason' => $reason,
                ]);

                return $new;
            }

            $shift = $old->shift;
            if (! $shift) {
                throw ValidationException::withMessages(['assignment' => 'Shift missing.']);
            }
            $new = $this->assignWorker->handle($shift, $replacement, $actor);

            Replacement::query()->create([
                'old_assignment_id' => $old->id,
                'new_assignment_id' => $new->id,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            $this->auditor->record($actor, 'assignment.replaced', $old, [
                'new_assignment_id' => $new->id,
                'reason' => $reason,
            ]);

            return $new;
        });
    }
}
