<?php

namespace App\Modules\Money\Services;

use App\Models\User;
use App\Modules\Subscriptions\Models\PlatformSetting;
use App\Modules\Subscriptions\Models\Subscription;

class Pricing
{
    public function commissionBps(): int
    {
        $value = (int) PlatformSetting::getValue('commission_bps', 1500);

        return max(0, min(5000, $value));
    }

    public function activeSubscription(?User $client): ?Subscription
    {
        if (! $client) {
            return null;
        }

        $sub = Subscription::query()
            ->with('plan.features')
            ->where('user_id', $client->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        return $sub;
    }

    /** @return array{labor_inr:int,commission_bps:int,commission_inr:int,total_inr:int,fee_waived:bool,subscription_id:?int,plan_name:?string} */
    public function quote(?User $client, int $laborInr): array
    {
        $labor = max(0, $laborInr);
        $sub = $this->activeSubscription($client);
        $waived = $sub?->hasFeature('waive_commission') ?? false;
        $bps = $this->commissionBps();
        $fee = $waived ? 0 : (int) round($labor * $bps / 10000);

        return [
            'labor_inr' => $labor,
            'commission_bps' => $waived ? 0 : $bps,
            'commission_inr' => $fee,
            'total_inr' => $labor + $fee,
            'fee_waived' => $waived,
            'subscription_id' => $sub?->id,
            'plan_name' => $sub?->plan?->name,
        ];
    }
}
