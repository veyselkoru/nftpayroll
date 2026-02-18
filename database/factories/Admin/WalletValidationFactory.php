<?php

namespace Database\Factories\Admin;

use App\Models\Admin\WalletValidation;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletValidationFactory extends Factory
{
    protected $model = WalletValidation::class;

    public function definition(): array
    {
        return [
            'wallet_address' => '0x'.strtolower($this->faker->regexify('[A-F0-9]{40}')),
            'network' => $this->faker->randomElement(['sepolia', 'ethereum', 'polygon']),
            'status' => $this->faker->randomElement(['valid', 'invalid']),
            'message' => $this->faker->sentence(),
            'checked_at' => now(),
        ];
    }
}
