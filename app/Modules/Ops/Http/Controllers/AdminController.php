<?php

namespace App\Modules\Ops\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Ops\Models\AuditLog;
use App\Modules\Ops\Models\NotificationLog;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Trust\Models\Report;
use App\Modules\Venues\Models\Venue;
use App\Modules\Venues\Models\VenueBooking;
use App\Modules\Venues\Models\VenuePartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'clients' => UserRole::query()->where('role', 'customer')->count(),
            'lawn_partners' => UserRole::query()->where('role', 'lawn')->count(),
            'banquet_partners' => UserRole::query()->where('role', 'banquet')->count(),
            'venues_published' => Venue::query()->where('status', 'published')->count(),
            'provider_types' => \App\Modules\Catalog\Models\ProviderType::query()->where('is_active', true)->count(),
            'bookings_open' => VenueBooking::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'payments_pending' => Payment::query()->where('status', 'pending')->count(),
            'reports_open' => Report::query()->where('status', 'open')->count(),
            'recent_bookings' => VenueBooking::query()
                ->with(['venue', 'customer', 'partner.venuePartnerProfile', 'payment'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (VenueBooking $b) => [
                    'slug' => $b->slug,
                    'status' => $b->status,
                    'city' => $b->venue?->city,
                    'guest_count' => $b->guest_count,
                    'total_inr' => $b->total_inr,
                    'advance_inr' => $b->advance_inr,
                    'starts_at' => $b->starts_at,
                    'ends_at' => $b->ends_at,
                    'venue' => $b->venue ? ['name' => $b->venue->name, 'venue_type' => $b->venue->venue_type] : null,
                    'customer' => $b->customer ? ['name' => $b->customer->name, 'phone' => $b->customer->phone] : null,
                    'partner' => $b->partner?->venuePartnerProfile ? ['company_name' => $b->partner->venuePartnerProfile->company_name] : null,
                ]),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json(
            User::query()->with(['roles', 'venuePartnerProfile'])->latest()->get()
        );
    }

    public function resolveReport(Request $request, Report $report, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,resolved,dismissed'],
        ]);
        $report->update(['status' => $data['status']]);
        $auditor->record($request->user(), 'report.updated', $report, $data);

        return response()->json($report->fresh(['reporter', 'reported']));
    }

    public function notifications(): JsonResponse
    {
        return response()->json(
            NotificationLog::query()->with('user')->latest()->limit(50)->get()
        );
    }

    public function reports(): JsonResponse
    {
        return response()->json(
            Report::query()->with(['reporter', 'reported', 'payout', 'serviceRequest'])->latest()->get()
        );
    }

    public function payouts(SettlePaymentAction $settle): JsonResponse
    {
        $settle->releaseDuePayouts();

        return response()->json(
            Payout::query()
                ->with([
                    'worker.venuePartnerProfile',
                    'serviceRequest.requester',
                ])
                ->latest()
                ->get()
        );
    }

    public function sendPayout(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $payout->update([
            'status' => 'sent',
            'paid_at' => $payout->paid_at ?: now(),
            'gateway_transfer_id' => $payout->gateway_transfer_id ?: 'upi-t1-'.$payout->id,
        ]);
        $auditor->record($request->user(), 'payout.sent', $payout);

        return response()->json($payout->fresh(['worker.venuePartnerProfile', 'serviceRequest.requester']));
    }

    public function releasePayout(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $payout->update([
            'status' => 'confirmed',
            'paid_at' => $payout->paid_at ?: now(),
            'confirmed_at' => now(),
            'disputed_at' => null,
            'gateway_transfer_id' => $payout->gateway_transfer_id ?: 'upi-dev-'.$payout->id,
        ]);
        $auditor->record($request->user(), 'payout.confirmed', $payout);

        if ($payout->id) {
            Report::query()
                ->where('payout_id', $payout->id)
                ->where('status', 'open')
                ->update(['status' => 'resolved']);
        }

        return response()->json($payout->fresh(['worker.venuePartnerProfile', 'serviceRequest.requester']));
    }

    public function audit(): JsonResponse
    {
        return response()->json(
            AuditLog::query()->with('actor')->latest()->limit(100)->get()
        );
    }
}
