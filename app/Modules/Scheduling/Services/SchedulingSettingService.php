<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\SchedulingSetting;

class SchedulingSettingService
{
    public function get(int $orgId): SchedulingSetting
    {
        return SchedulingSetting::firstOrCreate(
            ['organization_id' => $orgId],
            ['default_channels' => ['inapp']]
        );
    }

    public function update(int $orgId, array $data): SchedulingSetting
    {
        $setting = $this->get($orgId);
        $setting->update($data);
        return $setting->fresh();
    }
}
