<?php

namespace Database\Factories\Admin;

use App\Models\Admin\RoleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleDefinitionFactory extends Factory
{
    protected $model = RoleDefinition::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle(),
            'key' => $this->faker->unique()->slug(2),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'permissions' => ['dashboard.view', 'payroll.read'],
        ];
    }
}
