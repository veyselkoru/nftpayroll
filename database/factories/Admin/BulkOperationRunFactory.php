<?php

namespace Database\Factories\Admin;

use App\Models\Admin\BulkOperationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class BulkOperationRunFactory extends Factory
{
    protected $model = BulkOperationRun::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['payroll_import', 'wallet_check']),
            'status' => $this->faker->randomElement(['queued', 'running', 'failed', 'completed']),
            'total_items' => $this->faker->numberBetween(10, 500),
            'processed_items' => $this->faker->numberBetween(0, 400),
            'failed_items' => $this->faker->numberBetween(0, 30),
            'payload' => ['source' => 'csv'],
            'started_at' => now()->subHours(2),
            'finished_at' => now(),
        ];
    }
}
