<?php

namespace App\Modules\Money\Models;

use App\Models\User;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    public const AWAITING = ['sent', 'pending'];

    public const RECEIVED = ['confirmed', 'released'];

    protected $fillable = [
        'worker_user_id',
        'assignment_id',
        'service_request_id',
        'amount_inr',
        'upi_vpa',
        'status',
        'gateway_transfer_id',
        'paid_at',
        'due_at',
        'confirmed_at',
        'disputed_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'due_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    public function isAwaitingConfirm(): bool
    {
        return in_array($this->status, self::AWAITING, true);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
