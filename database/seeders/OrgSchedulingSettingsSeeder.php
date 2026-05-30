<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Organization;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
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
                    'executive_approval_required' => false,
                    'office_approval_required' => false,
                    'executive_approver_roles' => ['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'],
                    'office_approver_roles' => ['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'],
                    'updated_by' => 1,
                ]
            );
        }
    }
}
