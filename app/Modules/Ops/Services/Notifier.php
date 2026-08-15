<?php

namespace App\Modules\Ops\Services;

use App\Models\User;
use App\Modules\Ops\Models\NotificationLog;

class Notifier
{
    public function send(?User $user, string $template, array $payload = [], string $channel = 'whatsapp'): NotificationLog
    {
        return NotificationLog::query()->create([
            'user_id' => $user?->id,
            'channel' => $channel,
            'template' => $template,
            'payload' => $payload,
            'status' => 'logged',
        ]);
    }
}
