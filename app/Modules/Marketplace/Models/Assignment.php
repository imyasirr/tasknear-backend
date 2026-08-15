<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Events\Models\Attendance;
use App\Modules\Events\Models\EventShift;
use App\Modules\Money\Models\Payout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assignment extends Model
{
    protected $fillable = [
        'service_request_id',
        'event_shift_id',
        'worker_user_id',
        'status',
        'assigned_by',
        'invited_at',
        'expires_at',
        'responded_at',
    ];

    public const COMMITTED = ['accepted', 'checked_in', 'checked_out'];

    public const DEAD = ['declined', 'cancelled', 'replaced', 'expired'];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function isOpenOffer(): bool
    {
        return $this->status === 'invited' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EventShift::class, 'event_shift_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }
}
