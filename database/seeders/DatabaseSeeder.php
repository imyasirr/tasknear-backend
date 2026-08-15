<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            CitySeeder::class,
            AdminUserSeeder::class,
            SubscriptionSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
