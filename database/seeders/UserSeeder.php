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
                'name'     => 'System Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name'     => 'Demo User',
                'password' => Hash::make('password'),
                'role'     => 'user',
            ]
        );
    }
}
