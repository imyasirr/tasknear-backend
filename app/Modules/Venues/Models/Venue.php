<?php

namespace App\Modules\Venues\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'partner_user_id',
        'slug',
        'name',
        'venue_type',
        'description',
        'address',
        'city',
        'capacity_min',
        'capacity_max',
        'advance_percent',
        'price_per_day_inr',
        'amenities',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VenuePhoto::class)->orderBy('sort_order');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(VenueSlot::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(VenueBooking::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
