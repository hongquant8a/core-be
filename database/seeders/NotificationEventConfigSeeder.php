<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationEventEnum;
use Illuminate\Database\Seeder;

class NotificationEventConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Seed 1 row per event. module_key derived from event's module mapping.
        foreach (NotificationEventEnum::cases() as $event) {
            $moduleKey = $event->module()->value;
            NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value],
                ['enabled' => false, 'channels' => []]
            );
        }
    }
}
