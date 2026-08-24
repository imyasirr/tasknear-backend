<?php

namespace App\Modules\Venues\Services;

use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenueBooking;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VenueAvailabilityService
{
    /** @return Collection<int, VenueBooking> */
    public function blockingBookings(int $venueId, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = VenueBooking::query()
            ->where('venue_id', $venueId)
            ->whereIn('status', ['confirmed', 'awaiting_payment'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at');

        if ($from && $to) {
            $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
        }

        return $query->get();
    }

    public function assertAvailable(Venue $venue, Carbon $startsAt, Carbon $endsAt, ?int $ignoreBookingId = null): void
    {
        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'End must be after start.']);
        }

        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'Cannot book in the past.']);
        }

        $conflict = VenueBooking::query()
            ->where('venue_id', $venue->id)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->whereIn('status', ['confirmed', 'awaiting_payment'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'starts_at' => 'These dates are already booked. Pick another slot.',
            ]);
        }
    }

    public function priceForRange(Venue $venue, Carbon $startsAt, Carbon $endsAt): int
    {
        $seconds = max(1, $endsAt->getTimestamp() - $startsAt->getTimestamp());
        $days = max(1, (int) ceil($seconds / 86400));

        return (int) ($venue->price_per_day_inr * $days);
    }

    /** @return array{year:int,month:int,days:array<int,array{date:string,status:string,bookings:array}>} */
    public function calendarMonth(Venue $venue, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $bookings = VenueBooking::query()
            ->with(['customer'])
            ->where('venue_id', $venue->id)
            ->whereIn('status', ['confirmed', 'awaiting_payment'])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $end)
            ->where('ends_at', '>=', $start)
            ->get();

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $dayBookings = $bookings->filter(function (VenueBooking $b) use ($dayStart, $dayEnd) {
                return $b->starts_at < $dayEnd && $b->ends_at > $dayStart;
            })->map(fn (VenueBooking $b) => [
                'slug' => $b->slug,
                'status' => $b->status,
                'guest_count' => $b->guest_count,
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
                'customer_name' => $b->customer_name ?: $b->customer?->name,
                'customer_phone' => $b->customer_phone ?: $b->customer?->phone,
                'booked_by_partner' => $b->booked_by_partner,
            ])->values()->all();

            $days[$cursor->day] = [
                'date' => $cursor->toDateString(),
                'status' => count($dayBookings) > 0 ? 'booked' : 'free',
                'bookings' => $dayBookings,
            ];
            $cursor->addDay();
        }

        return [
            'venue_id' => $venue->id,
            'year' => $year,
            'month' => $month,
            'days' => $days,
        ];
    }

    /** @return list<array{starts_at:string,ends_at:string,status:string}> */
    public function bookedRanges(int $venueId): array
    {
        return VenueBooking::query()
            ->where('venue_id', $venueId)
            ->whereIn('status', ['confirmed', 'awaiting_payment'])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (VenueBooking $b) => [
                'starts_at' => $b->starts_at->toIso8601String(),
                'ends_at' => $b->ends_at->toIso8601String(),
                'status' => $b->status,
            ])
            ->all();
    }
}
