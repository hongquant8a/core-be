<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'channel' => 'sms',
            'status' => 'pending',
            'message_id' => null,
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'message_id' => (string) fake()->randomNumber(),
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Unknown error'): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
