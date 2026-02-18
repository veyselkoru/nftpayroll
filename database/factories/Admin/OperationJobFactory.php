<?php

namespace Database\Factories\Admin;

use App\Models\Admin\OperationJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperationJobFactory extends Factory
{
    protected $model = OperationJob::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['mint', 'sync', 'export']),
            'status' => $this->faker->randomElement(['queued', 'running', 'failed', 'cancelled', 'completed']),
            'attempts' => $this->faker->numberBetween(0, 3),
            'max_attempts' => 3,
            'payload' => ['foo' => 'bar'],
            'error_message' => $this->faker->boolean(20) ? $this->faker->sentence() : null,
            'started_at' => now()->subMinutes(rand(1, 120)),
            'finished_at' => $this->faker->boolean(50) ? now() : null,
        ];
    }
}
