<?php

namespace App\Modules\Money\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Venues\Actions\SettleVenueBookingPaymentAction;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Trust\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function devPay(
        Request $request,
        Payment $payment,
        SettlePaymentAction $settle,
        SettleVenueBookingPaymentAction $settleVenue,
    ): JsonResponse {
        if (! app()->environment('local')) {
            abort(404);
        }

        $user = $request->user();
        if ($payment->payer_id !== $user->id && ! $user->hasRole('admin')) {
            abort(403);
        }

        if ($payment->serviceRequest?->type === 'venue') {
            return response()->json($settleVenue->handle($payment, $user));
        }

        return response()->json($settle->handle($payment, $user));
    }

    public function earnings(Request $request): JsonResponse
    {
        $payouts = Payout::query()
            ->where('worker_user_id', $request->user()->id)
            ->with([
                'assignment.shift.category',
                'assignment.serviceRequest.eventDetail',
                'assignment.serviceRequest.taskDetail',
                'assignment.serviceRequest.requester',
            ])
            ->latest()
            ->get();

        return response()->json([
            'pending_inr' => $payouts->whereIn('status', Payout::AWAITING)->sum('amount_inr'),
            'paid_inr' => $payouts->whereIn('status', Payout::RECEIVED)->sum('amount_inr'),
            'disputed_inr' => $payouts->where('status', 'disputed')->sum('amount_inr'),
            'payouts' => $payouts,
        ]);
    }

    public function confirm(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $this->assertOwner($request, $payout);

        if (! $payout->isAwaitingConfirm()) {
            throw ValidationException::withMessages(['payout' => 'This payout is already closed.']);
        }

        $payout->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'paid_at' => $payout->paid_at ?: now(),
        ]);
        $auditor->record($request->user(), 'payout.confirmed', $payout);

        return response()->json($payout->fresh($this->payoutWith()));
    }

    public function dispute(Request $request, Payout $payout, Auditor $auditor): JsonResponse
    {
        $this->assertOwner($request, $payout);

        if (! $payout->isAwaitingConfirm()) {
            throw ValidationException::withMessages(['payout' => 'This payout cannot be disputed.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payout->loadMissing('assignment.serviceRequest');
        $clientId = $payout->assignment?->serviceRequest?->requester_id;
        if (! $clientId) {
            throw ValidationException::withMessages(['payout' => 'Client missing for this payout.']);
        }

        $report = Report::query()->create([
            'reporter_id' => $request->user()->id,
            'reported_id' => $clientId,
            'service_request_id' => $payout->assignment?->service_request_id,
            'payout_id' => $payout->id,
            'reason' => $data['reason'] ?: 'Payout not received after job checkout.',
            'status' => 'open',
        ]);

        $payout->update([
            'status' => 'disputed',
            'disputed_at' => now(),
        ]);
        $auditor->record($request->user(), 'payout.disputed', $payout, ['report_id' => $report->id]);

        return response()->json($payout->fresh($this->payoutWith()));
    }

    private function assertOwner(Request $request, Payout $payout): void
    {
        if ((int) $payout->worker_user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }

    /** @return list<string> */
    private function payoutWith(): array
    {
        return [
            'assignment.shift.category',
            'assignment.serviceRequest.eventDetail',
            'assignment.serviceRequest.taskDetail',
            'assignment.serviceRequest.requester',
        ];
    }
}
