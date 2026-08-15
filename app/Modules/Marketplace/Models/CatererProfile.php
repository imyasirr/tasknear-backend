<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatererProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'bio',
        'city',
        'gstin',
        'upi_vpa',
        'rating_avg',
        'jobs_completed',
        'status',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CatererSkill::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
