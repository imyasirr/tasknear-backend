<?php

namespace App\Modules\Money\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Money\Models\CheckoutSession;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Services\RazorpayGateway;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function config(RazorpayGateway $razorpay): JsonResponse
    {
        return response()->json([
            'razorpay_enabled' => $razorpay->isConfigured(),
            'key_id' => $razorpay->keyId(),
            'dev_pay_enabled' => app()->environment('local') && ! $razorpay->isConfigured(),
            'currency' => config('payments.checkout.currency', 'INR'),
            'company_name' => config('payments.checkout.company_name'),
        ]);
    }

    public function bookingCheckout(Request $request, Payment $payment, RazorpayGateway $razorpay): JsonResponse
    {
        $this->assertPayer($request, $payment);

        if ($payment->status === 'paid') {
            throw ValidationException::withMessages(['payment' => 'This payment is already completed.']);
        }

        if (! $razorpay->isConfigured()) {
            throw ValidationException::withMessages(['payment' => 'Online payments are not available yet.']);
        }

        $user = $request->user();
        $amountInr = (int) $payment->amount_inr;

        if ($payment->gateway_order_id && $payment->status === 'pending') {
            CheckoutSession::query()
                ->where('gateway_order_id', $payment->gateway_order_id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);
        }

        $order = $razorpay->createOrder(
            $amountInr,
            'pay-'.$payment->id.'-'.now()->timestamp,
            [
                'purpose' => CheckoutSession::PURPOSE_BOOKING,
                'payment_id' => (string) $payment->id,
                'user_id' => (string) $user->id,
            ],
        );

        $payment->update([
            'gateway' => 'razorpay',
            'gateway_order_id' => $order['id'],
        ]);

        CheckoutSession::query()->create([
            'user_id' => $user->id,
            'purpose' => CheckoutSession::PURPOSE_BOOKING,
            'reference_id' => $payment->id,
            'gateway_order_id' => $order['id'],
            'amount_inr' => $amountInr,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json($this->checkoutPayload($user, $order, $amountInr, 'Booking deposit'));
    }

    public function subscriptionCheckout(Request $request, SubscriptionPlan $plan, RazorpayGateway $razorpay): JsonResponse
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'This plan is not on sale.']);
        }

        if (! $razorpay->isConfigured()) {
            throw ValidationException::withMessages(['plan' => 'Online payments are not available yet.']);
        }

        $user = $request->user();
        $amountInr = (int) $plan->price_inr;

        CheckoutSession::query()
            ->where('user_id', $user->id)
            ->where('purpose', CheckoutSession::PURPOSE_SUBSCRIPTION)
            ->where('reference_id', $plan->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $order = $razorpay->createOrder(
            $amountInr,
            'sub-'.$plan->id.'-'.$user->id.'-'.now()->timestamp,
            [
                'purpose' => CheckoutSession::PURPOSE_SUBSCRIPTION,
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $user->id,
            ],
        );

        CheckoutSession::query()->create([
            'user_id' => $user->id,
            'purpose' => CheckoutSession::PURPOSE_SUBSCRIPTION,
            'reference_id' => $plan->id,
            'gateway_order_id' => $order['id'],
            'amount_inr' => $amountInr,
            'status' => 'pending',
            'meta' => ['plan_name' => $plan->name],
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json($this->checkoutPayload($user, $order, $amountInr, $plan->name.' plan'));
    }

    public function verify(
        Request $request,
        RazorpayGateway $razorpay,
        SettlePaymentAction $settle,
        Auditor $auditor,
    ): JsonResponse {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        if (! $razorpay->verifyPaymentSignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        )) {
            throw ValidationException::withMessages(['payment' => 'Payment verification failed.']);
        }

        $session = CheckoutSession::query()
            ->where('gateway_order_id', $data['razorpay_order_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $session || ! $session->isPending()) {
            throw ValidationException::withMessages(['payment' => 'Checkout session expired or not found.']);
        }

        return DB::transaction(function () use ($request, $data, $session, $settle, $auditor) {
            if ($session->purpose === CheckoutSession::PURPOSE_BOOKING) {
                return $this->completeBookingCheckout($request, $data, $session, $settle);
            }

            if ($session->purpose === CheckoutSession::PURPOSE_SUBSCRIPTION) {
                return $this->completeSubscriptionCheckout($request, $data, $session, $auditor);
            }

            throw ValidationException::withMessages(['payment' => 'Unknown checkout type.']);
        });
    }

    /** @param  array<string, string>  $data */
    private function completeBookingCheckout(Request $request, array $data, CheckoutSession $session, SettlePaymentAction $settle): JsonResponse
    {
        $payment = Payment::query()->find($session->reference_id);
        if (! $payment) {
            throw ValidationException::withMessages(['payment' => 'Payment record missing.']);
        }

        $this->assertPayer($request, $payment);

        if ($payment->status === 'paid') {
            $session->update(['status' => 'completed', 'completed_at' => now()]);

            return response()->json(['type' => 'booking', 'payment' => $payment]);
        }

        $payment->update([
            'gateway' => 'razorpay',
            'gateway_order_id' => $data['razorpay_order_id'],
            'gateway_payment_id' => $data['razorpay_payment_id'],
        ]);

        $settled = $settle->handle($payment->fresh(), $request->user());

        $session->update(['status' => 'completed', 'completed_at' => now()]);

        return response()->json(['type' => 'booking', 'payment' => $settled]);
    }

    /** @param  array<string, string>  $data */
    private function completeSubscriptionCheckout(Request $request, array $data, CheckoutSession $session, Auditor $auditor): JsonResponse
    {
        $plan = SubscriptionPlan::query()->find($session->reference_id);
        if (! $plan || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Plan is no longer available.']);
        }

        $user = $request->user();
        $user->assignRole('customer');

        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'replaced']);

        $sub = Subscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'amount_inr' => $plan->price_inr,
            'gateway' => 'razorpay',
            'gateway_payment_id' => $data['razorpay_payment_id'],
            'starts_at' => now(),
            'ends_at' => now()->addDays($plan->duration_days),
        ]);

        $session->update(['status' => 'completed', 'completed_at' => now()]);
        $auditor->record($user, 'subscription.bought', $sub, [
            'plan' => $plan->name,
            'order_id' => $data['razorpay_order_id'],
        ]);

        return response()->json([
            'type' => 'subscription',
            'subscription' => $sub->fresh('plan.features'),
        ], 201);
    }

    /** @param  array<string, mixed>  $order */
    private function checkoutPayload($user, array $order, int $amountInr, string $description): array
    {
        return [
            'key_id' => config('payments.razorpay.key_id'),
            'order_id' => $order['id'],
            'amount' => (int) ($order['amount'] ?? ($amountInr * 100)),
            'currency' => $order['currency'] ?? config('payments.checkout.currency', 'INR'),
            'name' => config('payments.checkout.company_name'),
            'description' => $description,
            'prefill' => [
                'name' => $user->name,
                'contact' => $user->phone,
            ],
        ];
    }

    private function assertPayer(Request $request, Payment $payment): void
    {
        $user = $request->user();
        if ((int) $payment->payer_id !== (int) $user->id && ! $user->hasRole('admin')) {
            abort(403);
        }
    }
}
