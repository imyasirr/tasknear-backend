<?php

namespace App\Modules\Tasks\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\ProviderTypes;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Tasks\Models\TaskDetail;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    public function __construct(
        private Auditor $auditor,
        private Pricing $pricing,
    ) {}

    public function handle(User $client, array $data): ServiceRequest
    {
        $client->assignRole('customer');
        $providerType = $data['provider_type'] ?? 'caterer';
        app(ProviderTypes::class)->assertActive($providerType);

        $category = Category::query()->findOrFail($data['category_id']);
        if (! in_array($category->vertical, ['task', 'both'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'category_id' => 'This category is not available for one-off tasks.',
            ]);
        }
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
                'provider_type' => $providerType,
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
                'category_id' => $category->id,
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
                'provider_type' => $providerType,
                'budget_inr' => $budget,
            ]);

            return $request->fresh(['taskDetail.category', 'payments', 'assignments.worker']);
        });
    }
}
