<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationEventEnum;
use Illuminate\Database\Seeder;

class NotificationEventConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Seed global defaults (configurable_type = null). Per-entity overrides created via UI later.
        foreach (NotificationEventEnum::values() as $eventKey) {
            NotificationEventConfig::firstOrCreate(
                ['configurable_type' => null, 'configurable_id' => null, 'event_key' => $eventKey],
                ['enabled' => false, 'channels' => []]
            );
        }
    }
}
