<?php

namespace App\Modules\Money\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    public const PURPOSE_BOOKING = 'booking';

    public const PURPOSE_SUBSCRIPTION = 'subscription';

    protected $fillable = [
        'user_id',
        'purpose',
        'reference_id',
        'gateway_order_id',
        'amount_inr',
        'status',
        'meta',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
