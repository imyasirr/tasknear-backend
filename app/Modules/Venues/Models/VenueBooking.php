<?php

namespace App\Modules\Venues\Models;

use App\Models\User;
use App\Modules\Money\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueBooking extends Model
{
    protected $fillable = [
        'slug',
        'venue_id',
        'slot_id',
        'customer_user_id',
        'partner_user_id',
        'guest_count',
        'total_inr',
        'advance_inr',
        'balance_inr',
        'notes',
        'booked_by_partner',
        'customer_name',
        'customer_phone',
        'starts_at',
        'ends_at',
        'status',
        'payment_id',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'booked_by_partner' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(VenueSlot::class, 'slot_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === 'awaiting_payment';
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, ['confirmed', 'completed'], true);
    }
}
