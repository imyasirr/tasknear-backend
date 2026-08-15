<?php

namespace Database\Seeders;

use App\Modules\Subscriptions\Models\PlatformSetting;
use App\Modules\Subscriptions\Models\SubscriptionFeature;
use App\Modules\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::setValue('commission_bps', 1500);
        PlatformSetting::setValue('vendor_offer_seconds', 180);

        $features = [
            ['slug' => 'waive_commission', 'name' => 'No booking fee', 'name_hi' => 'बुकिंग फीस नहीं', 'description' => 'Events and tasks have no extra commission while the plan is active.'],
            ['slug' => 'priority_support', 'name' => 'Priority support', 'name_hi' => 'प्राथमिकता सपोर्ट', 'description' => 'Ops picks up your bookings first if something goes wrong.'],
            ['slug' => 'multi_event', 'name' => 'Unlimited events', 'name_hi' => 'अनलिमिटेड इवेंट', 'description' => 'Post as many events and tasks as you need this month.'],
            ['slug' => 'team_seats', 'name' => 'Team seats', 'name_hi' => 'टीम सीट', 'description' => 'Share the booking desk with your catering team.'],
        ];

        $ids = [];
        foreach ($features as $row) {
            $ids[$row['slug']] = SubscriptionFeature::query()->updateOrCreate(['slug' => $row['slug']], $row)->id;
        }

        $plans = [
            [
                'name' => 'Starter',
                'name_hi' => 'स्टार्टर',
                'tagline' => 'Skip the 15% fee on every booking.',
                'price_inr' => 999,
                'duration_days' => 30,
                'sort' => 1,
                'features' => ['waive_commission'],
            ],
            [
                'name' => 'Pro',
                'name_hi' => 'प्रो',
                'tagline' => 'No fee + priority support for busy caterers.',
                'price_inr' => 2499,
                'duration_days' => 90,
                'sort' => 2,
                'features' => ['waive_commission', 'priority_support', 'multi_event'],
            ],
            [
                'name' => 'Business',
                'name_hi' => 'बिज़नेस',
                'tagline' => 'Yearly cover for venues and agencies.',
                'price_inr' => 7999,
                'duration_days' => 365,
                'sort' => 3,
                'features' => ['waive_commission', 'priority_support', 'multi_event', 'team_seats'],
            ],
        ];

        foreach ($plans as $row) {
            $slugs = $row['features'];
            unset($row['features']);
            $plan = SubscriptionPlan::query()->updateOrCreate(['name' => $row['name']], $row + ['is_active' => true]);
            $plan->features()->sync(array_map(fn ($slug) => $ids[$slug], $slugs));
        }
    }
}
