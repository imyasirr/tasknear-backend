<?php

namespace App\Models;

use App\Modules\Identity\Models\UserRole;
use App\Modules\Marketplace\Models\CatererProfile;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Workers\Models\WorkerProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'city',
        'locale',
        'avatar_path',
        'email',
        'password',
        'password_set_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_set_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    public function catererProfile(): HasOne
    {
        return $this->hasOne(CatererProfile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasRole(string $role): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('role', $role);
        }

        return $this->roles()->where('role', $role)->exists();
    }

    public function assignRole(string $role): void
    {
        $this->roles()->firstOrCreate(['role' => $role]);
    }
}
