<?php

namespace App\Modules\Ops\Services;

use App\Models\User;
use App\Modules\Ops\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Auditor
{
    public function record(?User $actor, string $action, ?Model $subject = null, array $payload = []): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload ?: null,
        ]);
    }
}
