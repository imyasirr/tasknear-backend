<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Money\Services\Pricing;
use App\Modules\Ops\Services\Auditor;
use App\Modules\Subscriptions\Models\Subscription;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(
            SubscriptionPlan::query()
                ->with('features')
                ->where('is_active', true)
                ->orderBy('sort')
                ->orderBy('price_inr')
                ->get()
        );
    }

    public function mine(Request $request, Pricing $pricing): JsonResponse
    {
        return response()->json([
            'quote' => $pricing->quote($request->user(), 1000),
            'active' => $pricing->activeSubscription($request->user()),
            'history' => Subscription::query()
                ->with('plan.features')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function quote(Request $request, Pricing $pricing): JsonResponse
    {
        $data = $request->validate([
            'labor_inr' => ['required', 'integer', 'min:0', 'max:5000000'],
        ]);

        return response()->json($pricing->quote($request->user(), (int) $data['labor_inr']));
    }

    public function buy(Request $request, SubscriptionPlan $plan, Auditor $auditor, Pricing $pricing): JsonResponse
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'This plan is not on sale.']);
        }

        $user = $request->user();
        $user->assignRole('customer');

        $sub = DB::transaction(function () use ($user, $plan) {
            Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'replaced']);

            return Subscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'amount_inr' => $plan->price_inr,
                'gateway' => 'manual',
                'gateway_payment_id' => 'sub-dev-'.$user->id.'-'.now()->timestamp,
                'starts_at' => now(),
                'ends_at' => now()->addDays($plan->duration_days),
            ]);
        });

        $auditor->record($user, 'subscription.bought', $sub, ['plan' => $plan->name]);

        return response()->json([
            'subscription' => $sub->fresh('plan.features'),
            'quote' => $pricing->quote($user->fresh(), 1000),
        ], 201);
    }
}
