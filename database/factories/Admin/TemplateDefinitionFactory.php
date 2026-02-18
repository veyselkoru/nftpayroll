<?php

namespace Database\Factories\Admin;

use App\Models\Admin\TemplateDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateDefinitionFactory extends Factory
{
    protected $model = TemplateDefinition::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['email', 'pdf', 'webhook']),
            'version' => $this->faker->numberBetween(1, 5),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'body' => $this->faker->paragraph(),
            'published_at' => now()->subDays(rand(0, 30)),
        ];
    }
}
