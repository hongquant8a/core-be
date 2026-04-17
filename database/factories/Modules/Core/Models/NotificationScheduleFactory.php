<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationScheduleFactory extends Factory
{
    protected $model = NotificationSchedule::class;

    public function definition(): array
    {
        return [
            'notification_event_config_id' => NotificationEventConfig::factory(),
            'moment' => null,
            'offset_minutes' => null,
            'channels' => ['sms', 'mail'],
            'label' => fake()->sentence(2),
            'sort_order' => 0,
        ];
    }

    public function instant(): static
    {
        return $this->state(['moment' => null, 'offset_minutes' => null]);
    }

    public function before(int $minutes = 60): static
    {
        return $this->state(['moment' => 'before', 'offset_minutes' => $minutes]);
    }

    public function on(): static
    {
        return $this->state(['moment' => 'on', 'offset_minutes' => null]);
    }

    public function after(int $minutes = 60): static
    {
        return $this->state(['moment' => 'after', 'offset_minutes' => $minutes]);
    }
}
