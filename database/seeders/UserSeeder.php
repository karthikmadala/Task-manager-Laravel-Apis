<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'address' => 'HQ',
                'phone' => '0000000000',
                'city' => 'AdminCity',
                'zip' => '00000',
                'consent_checkbox' => true,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'role' => 'user',
                'address' => 'Demo Street',
                'phone' => '1111111111',
                'city' => 'UserCity',
                'zip' => '11111',
                'consent_checkbox' => true,
            ]
        );

        User::factory()->count(8)->create();
    }
}
