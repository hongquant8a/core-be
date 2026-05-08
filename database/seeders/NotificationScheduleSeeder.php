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
        $this->seedTaskAssignment();
        $this->seedMeeting();
    }

    private function seedTaskAssignment(): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;

        // Non-reminder: mỗi event có 1 schedule "instant" (moment=null, offset=null) — per org.
        $nonReminder = [
            NotificationEventEnum::DocumentIssued->value,
            NotificationEventEnum::TaskAssigned->value,
            NotificationEventEnum::TaskCompleted->value,
            NotificationEventEnum::TaskConfirmed->value,
        ];
        $configs = NotificationEventConfig::where('module_key', $moduleKey)
            ->whereIn('event_key', $nonReminder)
            ->get();
        foreach ($configs as $config) {
            NotificationSchedule::firstOrCreate(
                ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                ['channels' => [], 'label' => 'Gửi tức thì', 'sort_order' => 0]
            );
        }

        // Reminder events: N schedules/event với moment + offset — per org.
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
            $reminderConfigs = NotificationEventConfig::where('module_key', $moduleKey)
                ->where('event_key', $eventKey)
                ->get();
            foreach ($reminderConfigs as $config) {
                foreach ($schedules as $s) {
                    NotificationSchedule::firstOrCreate(
                        ['notification_event_config_id' => $config->id, 'moment' => $s['moment'], 'offset_minutes' => $s['offset_minutes']],
                        ['channels' => [], 'label' => $s['label'], 'sort_order' => $s['sort_order']]
                    );
                }
            }
        }
    }

    private function seedMeeting(): void
    {
        $moduleKey = NotificationModuleEnum::Meeting->value;

        // Instant event: meeting_published — gửi giấy mời ngay khi chair publish meeting.
        $publishedConfigs = NotificationEventConfig::where('module_key', $moduleKey)
            ->where('event_key', NotificationEventEnum::MeetingPublished->value)
            ->get();
        foreach ($publishedConfigs as $config) {
            NotificationSchedule::firstOrCreate(
                ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                ['channels' => [], 'label' => 'Gửi giấy mời tức thì', 'sort_order' => 0]
            );
        }

        // Reminder events: trước 1 ngày + 30 phút, đến giờ, sau 5 phút.
        // Channels mặc định để [] — admin tự bật mail/sms/zalo/fcm qua UI.
        $reminderDefaults = [
            NotificationEventEnum::MeetingReminderBefore->value => [
                ['moment' => 'before', 'offset_minutes' => 1440, 'label' => 'Nhắc trước 1 ngày', 'sort_order' => 1],
                ['moment' => 'before', 'offset_minutes' => 30,   'label' => 'Nhắc trước 30 phút', 'sort_order' => 2],
            ],
            NotificationEventEnum::MeetingReminderOn->value => [
                ['moment' => 'on', 'offset_minutes' => null, 'label' => 'Đến giờ họp', 'sort_order' => 1],
            ],
            NotificationEventEnum::MeetingReminderAfter->value => [
                ['moment' => 'after', 'offset_minutes' => 5, 'label' => 'Sau 5 phút (nhắc kiểm tra biên bản)', 'sort_order' => 1],
            ],
        ];

        foreach ($reminderDefaults as $eventKey => $schedules) {
            $reminderConfigs = NotificationEventConfig::where('module_key', $moduleKey)
                ->where('event_key', $eventKey)
                ->get();
            foreach ($reminderConfigs as $config) {
                foreach ($schedules as $s) {
                    NotificationSchedule::firstOrCreate(
                        ['notification_event_config_id' => $config->id, 'moment' => $s['moment'], 'offset_minutes' => $s['offset_minutes']],
                        ['channels' => [], 'label' => $s['label'], 'sort_order' => $s['sort_order']]
                    );
                }
            }
        }
    }
}
