<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['phone' => '9999999999'],
            [
                'name' => 'TaskNear Admin',
                'email' => 'admin@tasknear.local',
                'password' => Hash::make('password'),
                'password_set_at' => now(),
                'city' => 'Lucknow',
                'locale' => 'en',
            ]
        );

        $admin->assignRole('admin');
    }
}
