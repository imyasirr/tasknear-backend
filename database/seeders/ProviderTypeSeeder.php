<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\ProviderType;
use Illuminate\Database\Seeder;

class ProviderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug' => 'caterer',
                'role' => 'caterer',
                'match_mode' => 'vendor',
                'name' => 'Catering company',
                'name_hi' => 'कैटरिंग कंपनी',
                'description' => 'Verified company sends waiters, helpers and event crew.',
                'description_hi' => 'वेरिफ़ाइड कंपनी वेटर, हेल्पर और इवेंट क्रू भेजती है।',
                'category_slugs' => ['waiter', 'helper', 'cleaner', 'cook', 'security', 'decorator'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'agency',
                'role' => 'agency',
                'match_mode' => 'vendor',
                'name' => 'Manpower agency',
                'name_hi' => 'मnpower एजेंसी',
                'description' => 'Agency supplies teams for events, loading and on-site work.',
                'description_hi' => 'एजेंसी इवेंट, लोडिंग और ऑन-साइट काम के लिए टीम भेजती है।',
                'category_slugs' => ['waiter', 'helper', 'cleaner', 'security', 'loader', 'task-helper', 'decorator'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'worker',
                'role' => 'worker',
                'match_mode' => 'worker',
                'name' => 'Individual worker',
                'name_hi' => 'अलग वर्कर',
                'description' => 'Helper, loader or general task — direct accept from app.',
                'description_hi' => 'हेल्पर, लोडर या जनरल टास्क — ऐप से सीधे स्वीकार।',
                'category_slugs' => ['task-helper', 'loader', 'waiter', 'helper', 'cleaner'],
                'sort_order' => 3,
            ],
            [
                'slug' => 'driver',
                'role' => 'driver',
                'match_mode' => 'worker',
                'name' => 'Driver partner',
                'name_hi' => 'ड्राइवर पार्टनर',
                'description' => 'Verified drivers for shifting, delivery and pickup runs.',
                'description_hi' => 'शिफ्टिंग, डिलीवरी और पिकअप के लिए वेरिफ़ाइड ड्राइवर।',
                'category_slugs' => ['driver', 'delivery-helper', 'loader'],
                'sort_order' => 4,
            ],
            [
                'slug' => 'home_pro',
                'role' => 'home_pro',
                'match_mode' => 'worker',
                'name' => 'Home services pro',
                'name_hi' => 'घर की सेवा विशेषज्ञ',
                'description' => 'Electrician, plumber and home repair professionals.',
                'description_hi' => 'इलेक्ट्रीशियन, प्लंबर और घर की मरम्मत के प्रोफ़ेशनल।',
                'category_slugs' => ['electrician', 'plumber'],
                'sort_order' => 5,
            ],
            [
                'slug' => 'venue_partner',
                'role' => 'venue_partner',
                'match_mode' => 'venue',
                'name' => 'Venue partner',
                'name_hi' => 'वेन्यू पार्टनर',
                'description' => 'List lawn or banquet halls with slots, gallery and online booking.',
                'description_hi' => 'लawn या बैंक्वेट हॉल लिस्ट करें — स्लॉट, गैलरी और ऑनलाइन बुकिंग।',
                'category_slugs' => [],
                'sort_order' => 6,
            ],
        ];

        foreach ($rows as $row) {
            ProviderType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true]),
            );
        }
    }
}
