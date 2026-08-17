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
            ['slug' => 'cook', 'name' => 'Cook / Kitchen helper', 'name_hi' => 'रसोइया / किचन हेल्पर', 'vertical' => 'event', 'default_rate_inr' => 850, 'default_duration_minutes' => 360],
            ['slug' => 'security', 'name' => 'Security / Bouncer', 'name_hi' => 'सुरक्षा / बाउंसर', 'vertical' => 'event', 'default_rate_inr' => 800, 'default_duration_minutes' => 360],
            ['slug' => 'decorator', 'name' => 'Setup / Decorator', 'name_hi' => 'सजावट / सेटअप', 'vertical' => 'event', 'default_rate_inr' => 750, 'default_duration_minutes' => 300],
            ['slug' => 'loader', 'name' => 'Loader / shifting', 'name_hi' => 'लोडिंग / शिफ्टिंग', 'vertical' => 'both', 'default_rate_inr' => 500, 'default_duration_minutes' => 120],
            ['slug' => 'task-helper', 'name' => 'General helper', 'name_hi' => 'सामान्य हेल्पर', 'vertical' => 'task', 'default_rate_inr' => 600, 'default_duration_minutes' => 180],
            ['slug' => 'driver', 'name' => 'Driver', 'name_hi' => 'ड्राइवर', 'vertical' => 'task', 'default_rate_inr' => 700, 'default_duration_minutes' => 180],
            ['slug' => 'delivery-helper', 'name' => 'Delivery helper', 'name_hi' => 'डिलीवरी हेल्पर', 'vertical' => 'task', 'default_rate_inr' => 550, 'default_duration_minutes' => 120],
            ['slug' => 'electrician', 'name' => 'Electrician', 'name_hi' => 'इलेक्ट्रीशियन', 'vertical' => 'task', 'default_rate_inr' => 900, 'default_duration_minutes' => 120],
            ['slug' => 'plumber', 'name' => 'Plumber', 'name_hi' => 'प्लंबर', 'vertical' => 'task', 'default_rate_inr' => 850, 'default_duration_minutes' => 120],
        ];

        foreach ($rows as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
