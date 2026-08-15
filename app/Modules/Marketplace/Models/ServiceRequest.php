<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Events\Models\EventDetail;
use App\Modules\Money\Models\Payment;
use App\Modules\Tasks\Models\TaskDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ServiceRequest extends Model
{
    protected $fillable = [
        'requester_id',
        'vendor_user_id',
        'type',
        'slug',
        'city',
        'lat',
        'lng',
        'address',
        'scheduled_start',
        'scheduled_end',
        'budget_inr',
        'required_workers',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        $query = static::query();
        if ($field === 'slug' || $field === $this->getRouteKeyName()) {
            return static::applyKey($query, (string) $value)->firstOrFail();
        }

        return $query->where($field, $value)->firstOrFail();
    }

    public static function applyKey($query, string $value)
    {
        if (ctype_digit($value)) {
            return $query->where(function ($q) use ($value) {
                $q->where((new static)->getKeyName(), $value)->orWhere('slug', $value);
            });
        }

        return $query->where('slug', $value);
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'booking';
        if (in_array($base, ['new', 'edit', 'create', 'fill'], true)) {
            $base .= '-booking';
        }

        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function vendorOffers(): HasMany
    {
        return $this->hasMany(VendorOffer::class);
    }

    public function eventDetail(): HasOne
    {
        return $this->hasOne(EventDetail::class);
    }

    public function taskDetail(): HasOne
    {
        return $this->hasOne(TaskDetail::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function vendorAttendance(): HasOne
    {
        return $this->hasOne(VendorAttendance::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(\App\Modules\Money\Models\Payout::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    public function presentVendor(bool $hideIdentities = true): static
    {
        $this->loadMissing(['vendor.catererProfile', 'vendorOffers.caterer.catererProfile']);

        $open = $this->vendorOffers
            ->where('status', 'invited')
            ->filter(fn (VendorOffer $offer) => ! $offer->expires_at || $offer->expires_at->isFuture())
            ->count();
        $accepted = (bool) $this->vendor_user_id;
        $company = $this->vendor?->catererProfile;

        $this->setAttribute('vendor_ring', [
            'ringing' => $open > 0 && ! $accepted,
            'count' => $open,
            'accepted' => $accepted,
        ]);
        $this->setAttribute('vendor_company', $accepted ? [
            'id' => $this->vendor?->id,
            'name' => $company?->company_name ?: $this->vendor?->name,
            'phone' => $this->vendor?->phone,
            'city' => $company?->city ?: $this->vendor?->city,
        ] : null);

        if (! $hideIdentities) {
            $this->setAttribute('vendor_offers', $this->vendorOffers->map(fn (VendorOffer $offer) => [
                'id' => $offer->id,
                'status' => $offer->status,
                'expires_at' => $offer->expires_at,
                'company' => $offer->caterer?->catererProfile?->company_name ?: $offer->caterer?->name,
                'phone' => $offer->caterer?->phone,
            ])->values());
        }

        $this->unsetRelation('vendor');
        $this->unsetRelation('vendorOffers');

        return $this;
    }

    public function presentAttendance(bool $showOtps = false): static
    {
        $this->loadMissing('vendorAttendance');
        $row = $this->vendorAttendance;

        $this->setAttribute('vendor_attendance', $row ? [
            'check_in_at' => $row->check_in_at,
            'check_out_at' => $row->check_out_at,
            'checked_in' => (bool) $row->check_in_at,
            'checked_out' => (bool) $row->check_out_at,
            'check_in_otp' => $showOtps ? $row->check_in_otp : null,
            'check_out_otp' => $showOtps ? $row->check_out_otp : null,
        ] : null);
        $this->unsetRelation('vendorAttendance');

        return $this;
    }

    public function transitionTo(string $status, ?User $actor = null, ?string $note = null): void
    {
        $from = $this->status;
        $this->update(['status' => $status]);
        $this->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $status,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
