<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'event_key' => 'document_issued',
            'notifiable_type' => 'App\\Test\\Dummy',
            'notifiable_id' => 1,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'context' => [],
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }
}
