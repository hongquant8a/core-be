<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use App\Modules\Scheduling\Models\SchedulingSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Scheduling - Cấu hình lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 */
class SchedulingSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        
        $legacy = SchedulingSetting::firstOrCreate(
            ['organization_id' => $orgId],
            ['default_channels' => ['inapp']]
        );

        $newSettings = OrgSchedulingSettings::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'executive_requires_approval' => false,
                'office_requires_approval'    => false,
                'executive_working_sessions'  => null,
                'office_working_sessions'     => null,
            ]
        );

        return $this->success([
            'organization_id'              => $orgId,
            'default_channels'             => $legacy->default_channels,
            'executive_requires_approval'  => (bool) $newSettings->executive_requires_approval,
            'office_requires_approval'     => (bool) $newSettings->office_requires_approval,
            'executive_working_sessions'   => $newSettings->executive_working_sessions,
            'office_working_sessions'      => $newSettings->office_working_sessions,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $orgId = getPermissionsTeamId();

        $legacy = SchedulingSetting::firstOrCreate(['organization_id' => $orgId]);
        $newSettings = OrgSchedulingSettings::firstOrCreate(['organization_id' => $orgId]);

        $newSettingsData = [];
        if ($request->has('executive_requires_approval')) {
            $newSettingsData['executive_requires_approval'] = $request->boolean('executive_requires_approval');
        }
        if ($request->has('office_requires_approval')) {
            $newSettingsData['office_requires_approval'] = $request->boolean('office_requires_approval');
        }
        if ($request->has('executive_working_sessions')) {
            $newSettingsData['executive_working_sessions'] = $request->input('executive_working_sessions');
        }
        if ($request->has('office_working_sessions')) {
            $newSettingsData['office_working_sessions'] = $request->input('office_working_sessions');
        }
        if ($newSettingsData) {
            $newSettings->update($newSettingsData);
        }

        $legacyData = [];
        if ($request->has('default_channels')) {
            $legacyData['default_channels'] = $request->input('default_channels');
        }
        if ($legacyData) {
            $legacy->update($legacyData);
        }

        return $this->show();
    }
}
