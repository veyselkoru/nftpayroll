<?php

namespace Database\Factories\Admin;

use App\Models\Admin\ExportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportJobFactory extends Factory
{
    protected $model = ExportJob::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['csv', 'xlsx', 'pdf']),
            'status' => $this->faker->randomElement(['queued', 'processing', 'ready', 'failed']),
            'file_path' => 'exports/'.$this->faker->uuid().'.csv',
            'filters' => ['from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()],
            'downloaded_at' => $this->faker->boolean(50) ? now() : null,
        ];
    }
}
