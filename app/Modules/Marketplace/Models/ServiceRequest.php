<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Events\Models\EventDetail;
use App\Modules\Marketplace\Models\Assignment;
use App\Modules\Money\Models\Payment;
use App\Modules\Tasks\Models\TaskDetail;
use App\Modules\Trust\Models\Rating;
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
        'provider_type',
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

        if ($row) {
            $this->setAttribute('vendor_attendance', [
                'check_in_at' => $row->check_in_at,
                'check_out_at' => $row->check_out_at,
                'checked_in' => (bool) $row->check_in_at,
                'checked_out' => (bool) $row->check_out_at,
                'check_in_otp' => $showOtps ? $row->check_in_otp : null,
                'check_out_otp' => $showOtps ? $row->check_out_otp : null,
            ]);
            $this->unsetRelation('vendorAttendance');

            return $this;
        }

        $crew = collect($this->getAttribute('client_crew') ?? [])
            ->filter(fn (array $member) => ! empty($member['attendance']));

        if ($crew->count() === 1) {
            $att = $crew->first()['attendance'];
            $this->setAttribute('vendor_attendance', [
                'check_in_at' => $att['check_in_at'] ?? null,
                'check_out_at' => $att['check_out_at'] ?? null,
                'checked_in' => ! empty($att['check_in_at']),
                'checked_out' => ! empty($att['check_out_at']),
                'check_in_otp' => $showOtps ? ($att['check_in_otp'] ?? null) : null,
                'check_out_otp' => $showOtps ? ($att['check_out_otp'] ?? null) : null,
            ]);
        } else {
            $this->setAttribute('vendor_attendance', null);
        }

        $this->unsetRelation('vendorAttendance');

        return $this;
    }

    public function presentClientCrew(bool $showOtps = false): static
    {
        $matchMode = app(\App\Modules\Catalog\Services\ProviderTypes::class)->matchMode($this->provider_type ?: 'caterer');
        if ($matchMode !== 'worker') {
            $this->setAttribute('client_crew', []);

            return $this;
        }

        $this->loadMissing(['assignments.worker', 'assignments.attendance']);
        $crew = $this->assignments
            ->whereIn('status', Assignment::COMMITTED)
            ->values()
            ->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'worker' => $assignment->worker ? [
                    'id' => $assignment->worker->id,
                    'name' => $assignment->worker->name,
                    'phone' => $assignment->worker->phone,
                ] : null,
                'attendance' => $assignment->attendance ? [
                    'check_in_at' => $assignment->attendance->check_in_at,
                    'check_out_at' => $assignment->attendance->check_out_at,
                    'check_in_otp' => $showOtps ? $assignment->attendance->check_in_otp : null,
                    'check_out_otp' => $showOtps ? $assignment->attendance->check_out_otp : null,
                ] : null,
            ]);

        $this->setAttribute('client_crew', $crew);

        return $this;
    }

    public function presentWorkerRing(): static
    {
        if (app(\App\Modules\Catalog\Services\ProviderTypes::class)->matchMode($this->provider_type ?: 'caterer') !== 'worker') {
            return $this;
        }

        $this->loadMissing('assignments');
        $open = $this->assignments
            ->where('status', 'invited')
            ->filter(fn (Assignment $a) => ! $a->expires_at || $a->expires_at->isFuture())
            ->count();
        $accepted = $this->assignments
            ->whereIn('status', Assignment::COMMITTED)
            ->count();

        $this->setAttribute('worker_ring', [
            'ringing' => $open > 0 && $accepted < $this->required_workers,
            'count' => $open,
            'accepted' => $accepted,
            'needed' => $this->required_workers,
        ]);
        $this->unsetRelation('assignments');

        return $this;
    }

    public function presentMyRatings(User $viewer): static
    {
        $ratings = Rating::query()
            ->where('service_request_id', $this->id)
            ->where('rater_id', $viewer->id)
            ->get(['ratee_id', 'assignment_id', 'stars', 'comment']);

        $this->setAttribute('my_ratings', $ratings);

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
