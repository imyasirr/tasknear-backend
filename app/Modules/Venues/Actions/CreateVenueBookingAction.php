<?php

namespace App\Modules\Venues\Actions;

use App\Models\User;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Money\Models\Payment;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenueBooking;
use App\Modules\Venues\Models\VenueSlot;
use App\Modules\Venues\Services\VenueAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateVenueBookingAction
{
    public function __construct(
        private Auditor $auditor,
        private VenueAvailabilityService $availability,
    ) {}

    /** @param  array{venue_id:int,starts_at:string,ends_at:string,guest_count:int,notes?:string|null}  $data */
    public function handle(User $customer, array $data): VenueBooking
    {
        $venue = Venue::query()->findOrFail($data['venue_id']);

        if (! $venue->isPublished()) {
            throw ValidationException::withMessages(['venue' => 'This venue is not available for booking.']);
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $this->availability->assertAvailable($venue, $startsAt, $endsAt);

        $guestCount = (int) $data['guest_count'];
        if ($guestCount < $venue->capacity_min || $guestCount > $venue->capacity_max) {
            throw ValidationException::withMessages([
                'guest_count' => "Guest count must be between {$venue->capacity_min} and {$venue->capacity_max}.",
            ]);
        }

        $customer->assignRole('customer');

        $totalInr = $this->availability->priceForRange($venue, $startsAt, $endsAt);
        $advancePercent = max(10, min(100, (int) $venue->advance_percent));
        $advanceInr = (int) max(1, round($totalInr * $advancePercent / 100));
        $balanceInr = max(0, $totalInr - $advanceInr);

        return DB::transaction(function () use ($customer, $venue, $data, $guestCount, $startsAt, $endsAt, $totalInr, $advanceInr, $balanceInr) {
            $slot = VenueSlot::query()->create([
                'venue_id' => $venue->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'price_inr' => $totalInr,
                'capacity' => $guestCount,
                'status' => 'held',
            ]);

            $serviceRequest = ServiceRequest::query()->create([
                'requester_id' => $customer->id,
                'vendor_user_id' => $venue->partner_user_id,
                'type' => 'venue',
                'provider_type' => 'venue_partner',
                'slug' => 'venue-'.Str::lower(Str::random(10)),
                'city' => $venue->city,
                'address' => $venue->address,
                'scheduled_start' => $startsAt,
                'scheduled_end' => $endsAt,
                'budget_inr' => $totalInr,
                'required_workers' => 0,
                'status' => 'awaiting_payment',
                'notes' => $data['notes'] ?? null,
            ]);

            $payment = Payment::query()->create([
                'service_request_id' => $serviceRequest->id,
                'payer_id' => $customer->id,
                'amount_inr' => $advanceInr,
                'labor_inr' => $advanceInr,
                'commission_inr' => 0,
                'commission_bps' => 0,
                'fee_waived' => true,
                'gateway' => 'manual',
                'status' => 'pending',
            ]);

            $booking = VenueBooking::query()->create([
                'slug' => 'vb-'.Str::lower(Str::random(10)),
                'venue_id' => $venue->id,
                'slot_id' => $slot->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'customer_user_id' => $customer->id,
                'partner_user_id' => $venue->partner_user_id,
                'guest_count' => $guestCount,
                'total_inr' => $totalInr,
                'advance_inr' => $advanceInr,
                'balance_inr' => $balanceInr,
                'notes' => $data['notes'] ?? null,
                'status' => 'awaiting_payment',
                'payment_id' => $payment->id,
            ]);

            $this->auditor->record($customer, 'venue_booking.created', $booking, [
                'venue' => $venue->name,
            ]);

            return $booking->fresh(['venue.photos', 'payment', 'customer', 'partner.venuePartnerProfile']);
        });
    }
}
