<?php

namespace Database\Factories\Admin;

use App\Models\Admin\NotificationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationEventFactory extends Factory
{
    protected $model = NotificationEvent::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->sentence(10),
            'channel' => $this->faker->randomElement(['in_app', 'email', 'sms', 'webhook']),
            'status' => $this->faker->randomElement(['queued', 'sent', 'failed']),
            'is_read' => $this->faker->boolean(),
            'read_at' => now(),
            'payload' => ['ref' => $this->faker->uuid()],
        ];
    }
}
