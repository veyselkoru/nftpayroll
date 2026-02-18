<?php

namespace Database\Factories\Admin;

use App\Models\Admin\SystemHealthSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemHealthSnapshotFactory extends Factory
{
    protected $model = SystemHealthSnapshot::class;

    public function definition(): array
    {
        return [
            'service' => $this->faker->randomElement(['api', 'queue', 'db', 'ipfs']),
            'status' => $this->faker->randomElement(['healthy', 'warning', 'down']),
            'latency_ms' => $this->faker->numberBetween(20, 2000),
            'error_rate' => $this->faker->randomFloat(2, 0, 20),
            'uptime_percent' => $this->faker->randomFloat(2, 80, 100),
            'incident_count' => $this->faker->numberBetween(0, 10),
            'captured_at' => now(),
            'meta' => ['region' => $this->faker->randomElement(['us-east-1', 'eu-central-1'])],
        ];
    }
}
