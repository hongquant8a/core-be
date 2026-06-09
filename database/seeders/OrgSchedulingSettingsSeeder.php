<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Organization;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use App\Modules\Scheduling\Models\SchedulingSetting;
use Illuminate\Database\Seeder;

class OrgSchedulingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            OrgSchedulingSettings::updateOrCreate(
                ['organization_id' => $org->id],
                [
                    'executive_requires_approval' => false,
                    'office_requires_approval' => false,
                ]
            );

            SchedulingSetting::updateOrCreate(
                ['organization_id' => $org->id],
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
    }
}
