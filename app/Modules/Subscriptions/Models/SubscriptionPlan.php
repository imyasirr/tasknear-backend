<?php

namespace App\Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'name_hi',
        'tagline',
        'price_inr',
        'duration_days',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(string $slug): bool
    {
        if ($this->relationLoaded('features')) {
            return $this->features->contains('slug', $slug);
        }

        return $this->features()->where('slug', $slug)->exists();
    }
}
