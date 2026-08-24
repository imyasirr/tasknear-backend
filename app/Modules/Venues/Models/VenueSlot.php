<?php

namespace App\Modules\Venues\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VenueSlot extends Model
{
    protected $fillable = [
        'venue_id',
        'starts_at',
        'ends_at',
        'price_inr',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(VenueBooking::class, 'slot_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->starts_at->isFuture();
    }
}
