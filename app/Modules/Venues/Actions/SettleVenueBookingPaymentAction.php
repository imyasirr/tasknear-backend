<?php

namespace App\Modules\Venues\Actions;

use App\Models\User;
use App\Modules\Money\Models\LedgerEntry;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Venues\Models\VenueBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleVenueBookingPaymentAction
{
    public function __construct(private Auditor $auditor) {}

    public function handle(Payment $payment, User $actor): Payment
    {
        $booking = VenueBooking::query()
            ->with(['slot', 'venue', 'customer', 'partner.venuePartnerProfile'])
            ->where('payment_id', $payment->id)
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages(['payment' => 'Venue booking not found for this payment.']);
        }

        if ($payment->status === 'paid' && $booking->isConfirmed()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $booking, $actor) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway' => $payment->gateway ?: 'manual',
                'gateway_payment_id' => $payment->gateway_payment_id ?: 'venue-'.now()->timestamp,
            ]);

            $booking->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $booking->slot?->update(['status' => 'booked']);

            $serviceRequest = $payment->serviceRequest;
            if ($serviceRequest && $serviceRequest->status === 'awaiting_payment') {
                $serviceRequest->update(['status' => 'confirmed']);
            }

            LedgerEntry::query()->create([
                'account_type' => 'platform',
                'account_id' => null,
                'direction' => 'credit',
                'amount_inr' => $payment->amount_inr,
                'entry_type' => 'venue_advance',
                'reference_type' => VenueBooking::class,
                'reference_id' => $booking->id,
            ]);

            Payout::query()->create([
                'worker_user_id' => $booking->partner_user_id,
                'service_request_id' => $payment->service_request_id,
                'amount_inr' => $payment->amount_inr,
                'upi_vpa' => $booking->partner?->venuePartnerProfile?->upi_vpa,
                'status' => 'scheduled',
                'due_at' => now()->addDay(),
            ]);

            $this->auditor->record($actor, 'venue_booking.paid', $booking, [
                'payment_id' => $payment->id,
                'customer_id' => $booking->customer_user_id,
                'partner_id' => $booking->partner_user_id,
            ]);

            return $payment->fresh();
        });
    }
}
