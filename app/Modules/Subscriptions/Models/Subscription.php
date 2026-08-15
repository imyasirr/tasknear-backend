<?php

namespace App\Modules\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'amount_inr',
        'gateway',
        'gateway_payment_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at?->isPast()
            && $this->ends_at?->isFuture();
    }

    public function hasFeature(string $slug): bool
    {
        return $this->isLive() && (bool) $this->plan?->hasFeature($slug);
    }
}
