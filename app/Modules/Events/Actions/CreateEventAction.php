<?php

namespace App\Modules\Events\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Services\ProviderTypes;
use App\Modules\Events\Models\EventDetail;
use App\Modules\Marketplace\Models\ServiceRequest;
use App\Modules\Money\Actions\SettlePaymentAction;
use App\Modules\Money\Models\Payment;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Services\Auditor;
use Illuminate\Support\Facades\DB;

class CreateEventAction
{
    public function __construct(
        private Auditor $auditor,
        private SettlePaymentAction $settle,
        private Pricing $pricing,
    ) {}

    public function handle(User $client, array $data): ServiceRequest
    {
        $client->assignRole('customer');
        $providerType = $data['provider_type'] ?? 'caterer';
        app(ProviderTypes::class)->assertActive($providerType);

        return DB::transaction(function () use ($client, $data, $providerType) {
            $request = ServiceRequest::query()->create([
                'requester_id' => $client->id,
                'type' => 'event',
                'provider_type' => $providerType,
                'slug' => ServiceRequest::uniqueSlug($data['title']),
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'scheduled_start' => $data['scheduled_start'],
                'scheduled_end' => $data['scheduled_end'],
                'status' => 'awaiting_payment',
                'notes' => $data['notes'] ?? null,
            ]);

            $details = EventDetail::query()->create([
                'service_request_id' => $request->id,
                'title' => $data['title'],
                'venue_name' => $data['venue_name'] ?? null,
                'guest_count' => $data['guest_count'] ?? null,
                'dress_code' => $data['dress_code'] ?? null,
                'meal_included' => (bool) ($data['meal_included'] ?? false),
            ]);

            $budget = 0;
            $workers = 0;

            foreach ($data['shifts'] as $shift) {
                $category = Category::query()->findOrFail($shift['category_id']);
                if (! in_array($category->vertical, ['event', 'both'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'shifts' => 'One or more categories are not available for events.',
                    ]);
                }
                $headcount = (int) $shift['headcount'];
                $rate = (int) ($shift['rate_per_worker_inr'] ?? $category->default_rate_inr);
                if ($rate < 100) {
                    $rate = $category->default_rate_inr;
                }
                $details->shifts()->create([
                    'category_id' => $category->id,
                    'headcount' => $headcount,
                    'start_at' => $shift['start_at'] ?? $data['scheduled_start'],
                    'end_at' => $shift['end_at'] ?? $data['scheduled_end'],
                    'rate_per_worker_inr' => $rate,
                    'status' => 'filling',
                ]);
                $budget += $rate * $headcount;
                $workers += $headcount;
            }

            $quote = $this->pricing->quote($client, $budget);
            $request->update([
                'budget_inr' => $budget,
                'required_workers' => $workers,
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

            $request->transitionTo('awaiting_payment', $client, 'Event created');
            $this->auditor->record($client, 'event.created', $request, [
                'provider_type' => $providerType,
                'budget_inr' => $budget,
                'required_workers' => $workers,
            ]);

            $created = $request->fresh(['eventDetail.shifts.category', 'payments']);
            $payment = $created->payments->first();
            if ($payment) {
                $this->settle->handle($payment, $client);
            }

            return $created->fresh(['eventDetail.shifts.category', 'eventDetail.shifts.assignments.worker', 'payments', 'assignments.worker']);
        });
    }
}
