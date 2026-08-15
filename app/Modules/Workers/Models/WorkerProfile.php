<?php

namespace App\Modules\Workers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'photo_path',
        'bio',
        'city',
        'service_radius_km',
        'rating_avg',
        'jobs_completed',
        'reliability_score',
        'status',
        'is_available',
        'upi_vpa',
        'pan_number',
        'aadhaar_number',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_name',
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

    public function documents(): HasMany
    {
        return $this->hasMany(WorkerDocument::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(WorkerSkill::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
