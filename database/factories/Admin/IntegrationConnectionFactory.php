<?php

namespace Database\Factories\Admin;

use App\Models\Admin\IntegrationConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationConnectionFactory extends Factory
{
    protected $model = IntegrationConnection::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'provider' => $this->faker->randomElement(['slack', 'sap', 'netsuite', 'quickbooks']),
            'status' => $this->faker->randomElement(['active', 'inactive', 'error']),
            'config' => ['endpoint' => $this->faker->url()],
            'last_test_at' => now()->subDay(),
            'last_test_status' => $this->faker->randomElement(['passed', 'failed']),
        ];
    }
}
