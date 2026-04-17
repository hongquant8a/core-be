<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationEventConfigFactory extends Factory
{
    protected $model = NotificationEventConfig::class;

    public function definition(): array
    {
        return [
            'module_key' => 'task_assignment',
            'organization_id' => Organization::factory(),
            'event_key' => 'document_issued',
            'enabled' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state(['enabled' => true]);
    }
}
