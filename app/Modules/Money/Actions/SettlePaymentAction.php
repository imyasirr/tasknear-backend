<?php

namespace App\Modules\Money\Actions;

use App\Models\User;
use App\Modules\Marketplace\Actions\AutoMatchAction;
use App\Modules\Money\Models\Commission;
use App\Modules\Money\Models\LedgerEntry;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Models\Payout;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlePaymentAction
{
    public function __construct(
        private Auditor $auditor,
        private AutoMatchAction $autoMatch,
        private Pricing $pricing,
    ) {}

    public function handle(Payment $payment, User $actor): Payment
    {
        if ($payment->status === 'paid') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $actor) {
            $request = $payment->serviceRequest()->with(['eventDetail.shifts', 'assignments.worker.workerProfile'])->first();
            if (! $request) {
                throw ValidationException::withMessages(['payment' => 'Request missing.']);
            }

            $labor = (int) ($payment->labor_inr ?: $payment->amount_inr);
            $quote = $this->pricing->quote($payment->payer ?: $actor, $labor);
            $commissionAmount = (int) ($payment->commission_inr ?: $quote['commission_inr']);
            $rateBps = (int) ($payment->commission_bps ?: $quote['commission_bps']);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway' => $payment->gateway ?: 'manual',
                'gateway_payment_id' => $payment->gateway_payment_id ?: 'dev-'.now()->timestamp,
                'labor_inr' => $labor,
                'commission_inr' => $commissionAmount,
                'commission_bps' => $rateBps,
                'fee_waived' => $quote['fee_waived'],
                'subscription_id' => $quote['subscription_id'],
                'amount_inr' => $quote['total_inr'],
            ]);

            Commission::query()->create([
                'payment_id' => $payment->id,
                'rate_bps' => $rateBps,
                'amount_inr' => $commissionAmount,
                'waived' => $quote['fee_waived'],
                'subscription_id' => $quote['subscription_id'],
            ]);

            LedgerEntry::query()->create([
                'account_type' => 'platform',
                'account_id' => null,
                'direction' => 'credit',
                'amount_inr' => $payment->amount_inr,
                'entry_type' => 'payment',
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
            ]);

            if ($commissionAmount > 0) {
                LedgerEntry::query()->create([
                    'account_type' => 'platform',
                    'account_id' => null,
                    'direction' => 'credit',
                    'amount_inr' => $commissionAmount,
                    'entry_type' => 'commission',
                    'reference_type' => Payment::class,
                    'reference_id' => $payment->id,
                ]);
            }

            if (in_array($request->status, ['awaiting_payment', 'draft', 'posted'], true)) {
                $request->transitionTo('matching', $actor, 'Deposit paid');
            }

            $this->auditor->record($actor, 'payment.paid', $payment);

            $paid = $payment->fresh('commission');
            $this->autoMatch->handle($request->fresh(), $actor);

            return $paid->fresh('commission');
        });
    }

    public function createPayoutsForRequest($request): void
    {
        $request->loadMissing('taskDetail');
        $assignments = $request->assignments()
            ->where('status', 'checked_out')
            ->with(['shift', 'worker.workerProfile'])
            ->get();

        foreach ($assignments as $assignment) {
            if (Payout::query()->where('assignment_id', $assignment->id)->exists()) {
                continue;
            }

            $rate = $assignment->shift?->rate_per_worker_inr
                ?? $request->taskDetail?->rate_per_worker_inr
                ?? (int) floor($request->budget_inr / max(1, $request->required_workers));

            Payout::query()->create([
                'worker_user_id' => $assignment->worker_user_id,
                'assignment_id' => $assignment->id,
                'amount_inr' => $rate,
                'upi_vpa' => $assignment->worker?->workerProfile?->upi_vpa,
                'status' => 'sent',
                'paid_at' => now(),
                'gateway_transfer_id' => 'upi-auto-'.$assignment->id,
            ]);

            LedgerEntry::query()->create([
                'account_type' => 'worker',
                'account_id' => $assignment->worker_user_id,
                'direction' => 'credit',
                'amount_inr' => $rate,
                'entry_type' => 'earning',
                'reference_type' => $assignment::class,
                'reference_id' => $assignment->id,
            ]);
        }
    }

    public function createVendorPayout($request): void
    {
        if (! $request->vendor_user_id) {
            return;
        }

        if (Payout::query()->where('service_request_id', $request->id)->exists()) {
            return;
        }

        $request->loadMissing(['payments', 'vendor.catererProfile']);
        $payment = $request->payments->firstWhere('status', 'paid') ?? $request->payments->first();
        $labor = (int) ($payment?->labor_inr ?: $request->budget_inr);

        Payout::query()->create([
            'worker_user_id' => $request->vendor_user_id,
            'service_request_id' => $request->id,
            'amount_inr' => $labor,
            'upi_vpa' => $request->vendor?->catererProfile?->upi_vpa,
            'status' => 'scheduled',
            'due_at' => now()->addDay(),
        ]);

        LedgerEntry::query()->create([
            'account_type' => 'vendor',
            'account_id' => $request->vendor_user_id,
            'direction' => 'credit',
            'amount_inr' => $labor,
            'entry_type' => 'earning',
            'reference_type' => $request::class,
            'reference_id' => $request->id,
        ]);
    }

    public function releaseDuePayouts(): void
    {
        Payout::query()
            ->where('status', 'scheduled')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->get()
            ->each(function (Payout $payout) {
                $payout->update([
                    'status' => 'sent',
                    'paid_at' => now(),
                    'gateway_transfer_id' => $payout->gateway_transfer_id ?: 'upi-t1-'.$payout->id,
                ]);
            });
    }
}
