<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'waiter', 'name' => 'Waiter / Server', 'name_hi' => 'वेटर', 'vertical' => 'event', 'default_rate_inr' => 900, 'default_duration_minutes' => 360],
            ['slug' => 'helper', 'name' => 'Event helper', 'name_hi' => 'इवेंट हेल्पर', 'vertical' => 'event', 'default_rate_inr' => 700, 'default_duration_minutes' => 360],
            ['slug' => 'cleaner', 'name' => 'Cleaner', 'name_hi' => 'क्लीनर', 'vertical' => 'event', 'default_rate_inr' => 700, 'default_duration_minutes' => 240],
            ['slug' => 'loader', 'name' => 'Loader / shifting', 'name_hi' => 'लोडिंग / शिफ्टिंग', 'vertical' => 'both', 'default_rate_inr' => 500, 'default_duration_minutes' => 120],
            ['slug' => 'task-helper', 'name' => 'General helper', 'name_hi' => 'सामान्य हेल्पर', 'vertical' => 'task', 'default_rate_inr' => 600, 'default_duration_minutes' => 180],
        ];

        foreach ($rows as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
