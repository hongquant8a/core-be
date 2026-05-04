<?php

namespace Tests\Concerns;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

trait InteractsWithNotifications
{
    /**
     * Seed event configs (disabled) cho default organization + set team context.
     * Mỗi event được stamp module_key đúng theo NotificationEventEnum::module().
     */
    protected function seedNotificationConfig(): void
    {
        $org = $this->resolveTestOrganization();
        setPermissionsTeamId($org->id);
        if (method_exists($this, 'withHeader')) {
            $this->withHeader('X-Organization-Id', (string) $org->id);
        }

        foreach (NotificationEventEnum::cases() as $event) {
            NotificationEventConfig::firstOrCreate(
                [
                    'module_key' => $event->module()->value,
                    'event_key' => $event->value,
                    'organization_id' => $org->id,
                ],
                ['enabled' => false]
            );
        }
    }

    /**
     * Enable 1 event với channels cho test. Non-reminder event → tự tạo schedule instant.
     */
    protected function enableEvent(string $eventKey, array $channels): NotificationEventConfig
    {
        $moduleKey = NotificationEventEnum::from($eventKey)->module()->value;
        $org = $this->resolveTestOrganization();
        $config = NotificationEventConfig::firstOrCreate(
            ['module_key' => $moduleKey, 'event_key' => $eventKey, 'organization_id' => $org->id],
            ['enabled' => true]
        );
        $config->update(['enabled' => true]);

        if (! str_contains($eventKey, 'reminder_')) {
            NotificationSchedule::firstOrCreate(
                ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                ['channels' => $channels, 'label' => 'Instant', 'sort_order' => 0]
            );
        }

        return $config;
    }

    /**
     * Tạo reminder schedule cho reminder event.
     */
    protected function addReminderSchedule(string $eventKey, string $moment, ?int $offsetMinutes, array $channels): NotificationSchedule
    {
        $moduleKey = NotificationEventEnum::from($eventKey)->module()->value;
        $org = $this->resolveTestOrganization();
        $config = NotificationEventConfig::where('module_key', $moduleKey)
            ->where('organization_id', $org->id)
            ->where('event_key', $eventKey)
            ->firstOrFail();

        return NotificationSchedule::create([
            'notification_event_config_id' => $config->id,
            'moment' => $moment,
            'offset_minutes' => $offsetMinutes,
            'channels' => $channels,
            'label' => "Test {$moment} {$offsetMinutes}",
            'sort_order' => 0,
        ]);
    }

    protected function resolveTestOrganization(): Organization
    {
        return Organization::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Organization', 'status' => 'active']
        );
    }
}
