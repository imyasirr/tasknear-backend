<?php

namespace App\Modules\Venues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Venues\Actions\CreatePartnerVenueBookingAction;
use App\Modules\Venues\Actions\CreateVenueBookingAction;
use App\Modules\Venues\Models\VenueBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueBookingController extends Controller
{
    public function store(Request $request, CreateVenueBookingAction $create): JsonResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $booking = $create->handle($request->user(), $data);

        return response()->json($this->present($booking), 201);
    }

    public function partnerStore(Request $request, CreatePartnerVenueBookingAction $create): JsonResponse
    {
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:50000'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'min:10', 'max:15'],
            'notes' => ['nullable', 'string', 'max:500'],
            'total_inr' => ['nullable', 'integer', 'min:0', 'max:5000000'],
        ]);

        $booking = $create->handle($request->user(), $data);

        return response()->json($this->present($booking), 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $rows = VenueBooking::query()
            ->with(['venue.photos', 'payment', 'partner.venuePartnerProfile'])
            ->where('customer_user_id', $request->user()->id)
            ->where('booked_by_partner', false)
            ->latest()
            ->get()
            ->map(fn (VenueBooking $b) => $this->present($b));

        return response()->json($rows);
    }

    public function partnerIndex(Request $request): JsonResponse
    {
        $rows = VenueBooking::query()
            ->with(['venue.photos', 'payment', 'customer'])
            ->where('partner_user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (VenueBooking $b) => $this->present($b));

        return response()->json($rows);
    }

    public function show(Request $request, VenueBooking $booking): JsonResponse
    {
        $this->assertViewer($request, $booking);
        $booking->load(['venue.photos', 'payment', 'customer', 'partner.venuePartnerProfile']);

        return response()->json($this->present($booking));
    }

    /** @return array<string, mixed> */
    private function present(VenueBooking $booking): array
    {
        $starts = $booking->starts_at ?? $booking->slot?->starts_at;
        $ends = $booking->ends_at ?? $booking->slot?->ends_at;

        return [
            'id' => $booking->id,
            'slug' => $booking->slug,
            'status' => $booking->status,
            'guest_count' => $booking->guest_count,
            'total_inr' => $booking->total_inr,
            'advance_inr' => $booking->advance_inr,
            'balance_inr' => $booking->balance_inr,
            'notes' => $booking->notes,
            'booked_by_partner' => (bool) $booking->booked_by_partner,
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'starts_at' => $starts?->toIso8601String(),
            'ends_at' => $ends?->toIso8601String(),
            'venue' => $booking->venue ? [
                'id' => $booking->venue->id,
                'slug' => $booking->venue->slug,
                'name' => $booking->venue->name,
                'address' => $booking->venue->address,
                'city' => $booking->venue->city,
                'cover_url' => $booking->venue->photos->first()?->url(),
            ] : null,
            'slot' => [
                'starts_at' => $starts?->toIso8601String(),
                'ends_at' => $ends?->toIso8601String(),
                'price_inr' => $booking->total_inr,
            ],
            'payment' => $booking->payment ? [
                'id' => $booking->payment->id,
                'amount_inr' => $booking->payment->amount_inr,
                'status' => $booking->payment->status,
            ] : null,
            'customer' => [
                'name' => $booking->customer_name ?: $booking->customer?->name,
                'phone' => $booking->customer_phone ?: $booking->customer?->phone,
            ],
            'partner' => $booking->partner ? [
                'name' => $booking->partner->name,
                'phone' => $booking->partner->phone,
                'company_name' => $booking->partner->venuePartnerProfile?->company_name,
            ] : null,
        ];
    }

    private function assertViewer(Request $request, VenueBooking $booking): void
    {
        $user = $request->user();
        if (
            (int) $booking->customer_user_id !== (int) $user->id
            && (int) $booking->partner_user_id !== (int) $user->id
            && ! $user->hasRole('admin')
        ) {
            abort(403);
        }
    }
}
