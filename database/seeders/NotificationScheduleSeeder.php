<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Database\Seeder;

class NotificationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;

        // Non-reminder: mỗi event có 1 schedule "instant" (moment=null, offset=null).
        $nonReminder = [
            NotificationEventEnum::DocumentIssued,
            NotificationEventEnum::TaskCompleted,
            NotificationEventEnum::TaskConfirmed,
        ];
        foreach ($nonReminder as $event) {
            $config = NotificationEventConfig::where('module_key', $moduleKey)
                ->where('event_key', $event->value)
                ->first();
            if (! $config) {
                continue;
            }
            NotificationSchedule::firstOrCreate(
                ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                ['channels' => [], 'label' => 'Gửi tức thì', 'sort_order' => 0]
            );
        }

        // Reminder events: N schedules/event với moment + offset.
        $reminderDefaults = [
            NotificationEventEnum::ReminderBefore->value => [
                ['moment' => 'before', 'offset_minutes' => 1440, 'label' => 'Nhắc trước 1 ngày', 'sort_order' => 1],
                ['moment' => 'before', 'offset_minutes' => 120,  'label' => 'Nhắc trước 2 giờ', 'sort_order' => 2],
            ],
            NotificationEventEnum::ReminderOn->value => [
                ['moment' => 'on', 'offset_minutes' => null, 'label' => 'Đến hạn', 'sort_order' => 1],
            ],
            NotificationEventEnum::ReminderAfter->value => [
                ['moment' => 'after', 'offset_minutes' => 1440, 'label' => 'Trễ 1 ngày', 'sort_order' => 1],
            ],
        ];

        foreach ($reminderDefaults as $eventKey => $schedules) {
            $config = NotificationEventConfig::where('module_key', $moduleKey)
                ->where('event_key', $eventKey)
                ->first();
            if (! $config) {
                continue;
            }
            foreach ($schedules as $s) {
                NotificationSchedule::firstOrCreate(
                    ['notification_event_config_id' => $config->id, 'moment' => $s['moment'], 'offset_minutes' => $s['offset_minutes']],
                    ['channels' => [], 'label' => $s['label'], 'sort_order' => $s['sort_order']]
                );
            }
        }
    }
}
