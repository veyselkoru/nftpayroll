<?php

namespace Database\Factories\Admin;

use App\Models\Admin\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'module' => $this->faker->randomElement(['security', 'exports', 'integrations', 'roles']),
            'action' => $this->faker->randomElement(['create', 'update', 'delete', 'webhook']),
            'status' => $this->faker->randomElement(['success', 'failed']),
            'ip_address' => $this->faker->ipv4(),
            'meta' => ['source' => 'factory'],
        ];
    }
}
