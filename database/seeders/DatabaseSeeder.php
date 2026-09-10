<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProviderTypeSeeder::class,
            CitySeeder::class,
            AdminUserSeeder::class,
            VenueDemoSeeder::class,
        ]);
    }
}
