<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Lucknow', 'state' => 'Uttar Pradesh', 'is_active' => true],
            ['name' => 'Kanpur', 'state' => 'Uttar Pradesh', 'is_active' => true],
            ['name' => 'Varanasi', 'state' => 'Uttar Pradesh', 'is_active' => true],
            ['name' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'is_active' => false],
        ];

        foreach ($rows as $row) {
            City::query()->updateOrCreate(['name' => $row['name']], $row);
        }
    }
}
