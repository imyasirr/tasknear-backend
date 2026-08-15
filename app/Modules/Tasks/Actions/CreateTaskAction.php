<?php

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Tasks\Models\TaskDetail;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    public function __construct(
        private Auditor $auditor,
        private SettlePaymentAction $settle,
        private Pricing $pricing,
    ) {}

    public function handle(User $client, array $data): ServiceRequest
    {
        $client->assignRole('customer');
        $category = Category::query()->findOrFail($data['category_id']);
        $workers = (int) ($data['required_workers'] ?? 1);
        $rate = (int) ($data['rate_per_worker_inr'] ?? $category->default_rate_inr);
        if ($rate < 100) {
            $rate = $category->default_rate_inr;
        }
        $budget = $rate * $workers;
        $quote = $this->pricing->quote($client, $budget);

        return DB::transaction(function () use ($client, $data, $category, $workers, $budget, $rate, $quote) {
            $request = ServiceRequest::query()->create([
                'requester_id' => $client->id,
                'type' => 'task',
                'slug' => ServiceRequest::uniqueSlug($data['title']),
                'city' => $data['city'],
                'address' => $data['pickup_address'] ?? $data['address'] ?? null,
                'scheduled_start' => $data['scheduled_start'],
                'scheduled_end' => $data['scheduled_end'] ?? $data['scheduled_start'],
                'budget_inr' => $budget,
                'required_workers' => $workers,
                'status' => 'awaiting_payment',
                'notes' => $data['notes'] ?? null,
            ]);

            TaskDetail::query()->create([
                'service_request_id' => $request->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'pickup_address' => $data['pickup_address'] ?? null,
                'drop_address' => $data['drop_address'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $category->default_duration_minutes,
                'rate_per_worker_inr' => $rate,
                'proof_required' => (bool) ($data['proof_required'] ?? false),
            ]);

            Payment::query()->create([
                'service_request_id' => $request->id,
                'payer_id' => $client->id,
                'amount_inr' => $quote['total_inr'],
                'labor_inr' => $quote['labor_inr'],
                'commission_inr' => $quote['commission_inr'],
                'commission_bps' => $quote['commission_bps'],
                'fee_waived' => $quote['fee_waived'],
                'subscription_id' => $quote['subscription_id'],
                'gateway' => 'manual',
                'status' => 'pending',
            ]);

            $request->transitionTo('awaiting_payment', $client, 'Task created');
            $this->auditor->record($client, 'task.created', $request, [
                'category' => $category->slug,
                'budget_inr' => $budget,
            ]);

            $created = $request->fresh(['taskDetail', 'payments']);
            $payment = $created->payments->first();
            if ($payment) {
                $this->settle->handle($payment, $client);
            }

            return $created->fresh(['taskDetail', 'payments', 'assignments.worker']);
        });
    }
}
