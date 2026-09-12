<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\Organization;
use App\Services\Notification\Enums\NotificationEventEnum;
use Illuminate\Database\Seeder;

class NotificationEventConfigSeeder extends Seeder
{
    /**
     * Event instant (gửi ngay, moment=null) cần backfill lịch cho org cũ khi thêm
     * event mới vào enum — nếu thiếu row schedule thì resolveChannels trả rỗng và
     * event không bao giờ gửi dù đã bật.
     */
    private const INSTANT_SCHEDULE_LABELS = [
        'report_submitted' => 'Thông báo ngay khi có báo cáo mới',
        'task_rejected' => 'Thông báo ngay khi bị trả lại',
        'deadline_extension_requested' => 'Thông báo ngay khi có yêu cầu gia hạn',
        'deadline_extension_reviewed' => 'Thông báo ngay khi yêu cầu gia hạn được xử lý',
        'task_deadline_changed' => 'Thông báo ngay khi thời hạn thay đổi',
        'task_status_changed' => 'Thông báo ngay khi tạm dừng, huỷ hoặc mở lại',
        'note_added' => 'Thông báo ngay khi có trao đổi mới',
        'petition_created' => 'Thông báo ngay khi có đơn thư mới',
        'petition_status_changed' => 'Thông báo ngay khi đơn thư đổi trạng thái',
        'document_updated' => 'Thông báo ngay khi văn bản được cập nhật',
    ];

    public function run(): void
    {
        // Seed 1 row per (event, organization). module_key derived from event's module mapping.
        $organizations = Organization::query()->pluck('id');

        foreach ($organizations as $organizationId) {
            foreach (NotificationEventEnum::cases() as $event) {
                $config = NotificationEventConfig::firstOrCreate(
                    [
                        'module_key' => $event->module()->value,
                        'event_key' => $event->value,
                        'organization_id' => $organizationId,
                    ],
                    ['enabled' => false]
                );

                // Backfill lịch "gửi ngay" cho các event instant mới thêm.
                if (isset(self::INSTANT_SCHEDULE_LABELS[$event->value])) {
                    NotificationSchedule::firstOrCreate(
                        ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                        ['channels' => [], 'label' => self::INSTANT_SCHEDULE_LABELS[$event->value], 'sort_order' => 0],
                    );
                }
            }
        }
    }
}
