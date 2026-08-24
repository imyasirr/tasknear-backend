<?php

namespace App\Modules\Venues\Actions;

use App\Models\User;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenueBooking;
use App\Modules\Venues\Models\VenueSlot;
use App\Modules\Venues\Services\VenueAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePartnerVenueBookingAction
{
    public function __construct(
        private Auditor $auditor,
        private VenueAvailabilityService $availability,
    ) {}

    /** @param  array{venue_id:int,starts_at:string,ends_at:string,guest_count:int,customer_name:string,customer_phone:string,notes?:string|null,total_inr?:int|null}  $data */
    public function handle(User $partner, array $data): VenueBooking
    {
        $venue = Venue::query()->where('id', $data['venue_id'])->where('partner_user_id', $partner->id)->first();
        if (! $venue) {
            abort(403);
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $this->availability->assertAvailable($venue, $startsAt, $endsAt);

        $guestCount = (int) $data['guest_count'];
        $totalInr = (int) ($data['total_inr'] ?? $this->availability->priceForRange($venue, $startsAt, $endsAt));

        return DB::transaction(function () use ($partner, $venue, $data, $guestCount, $startsAt, $endsAt, $totalInr) {
            $slot = VenueSlot::query()->create([
                'venue_id' => $venue->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'price_inr' => $totalInr,
                'capacity' => $guestCount,
                'status' => 'booked',
            ]);

            $booking = VenueBooking::query()->create([
                'slug' => 'vb-'.Str::lower(Str::random(10)),
                'venue_id' => $venue->id,
                'slot_id' => $slot->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'customer_user_id' => $partner->id,
                'partner_user_id' => $partner->id,
                'guest_count' => $guestCount,
                'total_inr' => $totalInr,
                'advance_inr' => 0,
                'balance_inr' => $totalInr,
                'notes' => $data['notes'] ?? null,
                'booked_by_partner' => true,
                'customer_name' => $data['customer_name'],
                'customer_phone' => preg_replace('/\D+/', '', $data['customer_phone']),
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $this->auditor->record($partner, 'venue_booking.partner_created', $booking);

            return $booking->fresh(['venue.photos', 'customer', 'partner.venuePartnerProfile']);
        });
    }
}
