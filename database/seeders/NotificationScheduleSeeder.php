<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationSchedule;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Database\Seeder;

class NotificationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // Default schedules cho module TaskAssignment
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;

        $defaults = [
            ['moment' => 'before', 'offset_minutes' => 1440, 'channels' => ['mail'], 'label' => 'Nhắc trước 1 ngày', 'sort_order' => 1],
            ['moment' => 'before', 'offset_minutes' => 120,  'channels' => ['sms', 'fcm'], 'label' => 'Nhắc trước 2 giờ', 'sort_order' => 2],
            ['moment' => 'on',     'offset_minutes' => null, 'channels' => ['sms', 'mail', 'fcm'], 'label' => 'Đến hạn', 'sort_order' => 3],
            ['moment' => 'after',  'offset_minutes' => 1440, 'channels' => ['mail'], 'label' => 'Trễ 1 ngày', 'sort_order' => 4],
        ];

        foreach ($defaults as $d) {
            NotificationSchedule::firstOrCreate(
                ['module_key' => $moduleKey, 'moment' => $d['moment'], 'offset_minutes' => $d['offset_minutes']],
                ['channels' => $d['channels'], 'enabled' => true, 'label' => $d['label'], 'sort_order' => $d['sort_order']]
            );
        }
    }
}
