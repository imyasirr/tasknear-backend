<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Subscriptions\Models\PlatformSetting;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionFeature;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json([
            'commission_bps' => (int) PlatformSetting::getValue('commission_bps', 1500),
            'features' => SubscriptionFeature::query()->orderBy('name')->get(),
        ]);
    }

    public function updateSettings(Request $request, Auditor $auditor): JsonResponse
    {
        $data = $request->validate([
            'commission_bps' => ['required', 'integer', 'min:0', 'max:5000'],
        ]);
        PlatformSetting::setValue('commission_bps', $data['commission_bps']);
        $auditor->record($request->user(), 'settings.commission', null, $data);

        return $this->settings();
    }

    public function plans(): JsonResponse
    {
        return response()->json(
            SubscriptionPlan::query()->with('features')->orderBy('sort')->orderBy('id')->get()
        );
    }

    public function storePlan(Request $request, Auditor $auditor): JsonResponse
    {
        $data = $this->planRules($request);
        $plan = SubscriptionPlan::query()->create(collect($data)->except('feature_ids')->all());
        $plan->features()->sync($data['feature_ids'] ?? []);
        $auditor->record($request->user(), 'plan.created', $plan);

        return response()->json($plan->fresh('features'), 201);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan, Auditor $auditor): JsonResponse
    {
        $data = $this->planRules($request, false);
        $plan->update(collect($data)->except('feature_ids')->all());
        if (array_key_exists('feature_ids', $data)) {
            $plan->features()->sync($data['feature_ids'] ?? []);
        }
        $auditor->record($request->user(), 'plan.updated', $plan);

        return response()->json($plan->fresh('features'));
    }

    public function subscriptions(): JsonResponse
    {
        return response()->json(
            Subscription::query()->with(['user', 'plan'])->latest()->get()
        );
    }

    private function planRules(Request $request, bool $required = true): array
    {
        $req = $required ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'name_hi' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'price_inr' => [$req, 'integer', 'min:0', 'max:500000'],
            'duration_days' => [$req, 'integer', 'min:1', 'max:1095'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer', 'exists:subscription_features,id'],
        ]);
    }
}
