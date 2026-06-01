<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\SchedulingSetting;

class SchedulingSettingService
{
    public function get(int $orgId): SchedulingSetting
    {
        return SchedulingSetting::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'approval_enabled'      => false,
                'approval_module_types' => [],
                'default_channels'      => ['inapp'],
                'working_sessions'      => [
                    'MORNING'   => ['start' => '07:30', 'end' => '11:30'],
                    'AFTERNOON' => ['start' => '13:30', 'end' => '17:00'],
                    'EVENING'   => ['start' => '19:00', 'end' => '21:00'],
                ],
            ]
        );
    }

    public function update(int $orgId, array $data): SchedulingSetting
    {
        $setting = $this->get($orgId);
        $setting->update($data);
        return $setting->fresh();
    }
}
