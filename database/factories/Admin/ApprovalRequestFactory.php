<?php

namespace Database\Factories\Admin;

use App\Models\Admin\ApprovalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'type' => $this->faker->randomElement(['payroll', 'wallet', 'integration']),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'payload' => ['amount' => $this->faker->randomFloat(2, 100, 10000)],
        ];
    }
}
