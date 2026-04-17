<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\Organization;
use App\Services\Notification\Enums\NotificationEventEnum;
use Illuminate\Database\Seeder;

class NotificationEventConfigSeeder extends Seeder
{
    public function run(): void
    {
        // Seed 1 row per (event, organization). module_key derived from event's module mapping.
        $organizations = Organization::query()->pluck('id');

        foreach ($organizations as $organizationId) {
            foreach (NotificationEventEnum::cases() as $event) {
                NotificationEventConfig::firstOrCreate(
                    [
                        'module_key' => $event->module()->value,
                        'event_key' => $event->value,
                        'organization_id' => $organizationId,
                    ],
                    ['enabled' => false]
                );
            }
        }
    }
}
