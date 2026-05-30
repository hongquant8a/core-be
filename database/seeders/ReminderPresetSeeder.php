<?php

namespace Database\Seeders;

use App\Modules\Scheduling\Models\ReminderPreset;
use Illuminate\Database\Seeder;

class ReminderPresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'organization_id' => null,
                'moment' => 'before',
                'offset_minutes' => 15,
                'label' => 'Trước 15 phút',
                'channels' => ['fcm', 'zalo', 'inapp'],
            ],
            [
                'organization_id' => null,
                'moment' => 'before',
                'offset_minutes' => 30,
                'label' => 'Trước 30 phút',
                'channels' => ['fcm', 'zalo', 'inapp'],
            ],
            [
                'organization_id' => null,
                'moment' => 'before',
                'offset_minutes' => 60,
                'label' => 'Trước 1 giờ',
                'channels' => ['fcm', 'zalo', 'inapp'],
            ],
            [
                'organization_id' => null,
                'moment' => 'before',
                'offset_minutes' => 1440,
                'label' => 'Trước 1 ngày',
                'channels' => ['fcm', 'zalo', 'inapp'],
            ],
        ];

        foreach ($presets as $preset) {
            ReminderPreset::updateOrCreate(
                [
                    'organization_id' => $preset['organization_id'],
                    'offset_minutes' => $preset['offset_minutes'],
                    'moment' => $preset['moment'],
                ],
                [
                    'label' => $preset['label'],
                    'channels' => $preset['channels'],
                    'created_by' => 1, // Fallback to user ID 1
                ]
            );
        }
    }
}
