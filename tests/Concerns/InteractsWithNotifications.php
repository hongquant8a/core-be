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
     * Seed 6 event configs (disabled) + schedules instant cho non-reminder.
     * Also sets default X-Organization-Id header for HTTP tests using the default organization.
     */
    protected function seedNotificationConfig(): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;
        foreach (NotificationEventEnum::cases() as $event) {
            NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value],
                ['enabled' => false]
            );
        }

        $defaultOrg = Organization::where('slug', 'default')->first();
        if ($defaultOrg && method_exists($this, 'withHeader')) {
            $this->withHeader('X-Organization-Id', (string) $defaultOrg->id);
            setPermissionsTeamId($defaultOrg->id);
        }
    }

    /**
     * Enable 1 event với channels cho test. Non-reminder event → tự tạo schedule instant.
     */
    protected function enableEvent(string $eventKey, array $channels): NotificationEventConfig
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;
        $config = NotificationEventConfig::firstOrCreate(
            ['module_key' => $moduleKey, 'event_key' => $eventKey],
            ['enabled' => true]
        );
        $config->update(['enabled' => true]);

        if (! str_starts_with($eventKey, 'reminder_')) {
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
        $config = NotificationEventConfig::where('module_key', NotificationModuleEnum::TaskAssignment->value)
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
}
