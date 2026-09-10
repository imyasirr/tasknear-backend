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
                'slug' => 'lawn',
                'role' => 'lawn',
                'match_mode' => 'venue',
                'name' => 'Lawn partner',
                'name_hi' => 'लawn पार्टनर',
                'description' => 'List your lawn with slots, gallery and online booking.',
                'description_hi' => 'अपना lawn लिस्ट करें — स्लॉट, गैलरी और ऑनलाइन बुकिंग।',
                'category_slugs' => [],
                'sort_order' => 1,
            ],
            [
                'slug' => 'banquet',
                'role' => 'banquet',
                'match_mode' => 'venue',
                'name' => 'Banquet hall partner',
                'name_hi' => 'बैंक्वेट हॉल पार्टनर',
                'description' => 'List your banquet hall with slots, gallery and online booking.',
                'description_hi' => 'अपना बैंक्वेट हॉल लिस्ट करें — स्लॉट, गैलरी और ऑनलाइन बुकिंग।',
                'category_slugs' => [],
                'sort_order' => 2,
            ],
        ];

        foreach ($rows as $row) {
            ProviderType::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true]),
            );
        }

        ProviderType::query()
            ->whereNotIn('slug', ['lawn', 'banquet'])
            ->update(['is_active' => false]);
    }
}
