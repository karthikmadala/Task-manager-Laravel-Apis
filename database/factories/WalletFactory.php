<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'chain_type' => 'eth',
            'wallet_type' => 'external',
            'address' => '0x' . $this->faker->unique()->regexify('[0-9a-f]{40}'),
            'label' => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }
}
