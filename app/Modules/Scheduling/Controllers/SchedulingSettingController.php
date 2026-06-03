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

        $newSettings = OrgSchedulingSettings::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'requires_approval' => false,
            ]
        );

        return $this->success([
            'organization_id'             => $orgId,
            'approval_enabled'            => $legacy->approval_enabled,
            'approval_module_types'       => $legacy->approval_module_types,
            'default_channels'            => $legacy->default_channels,
            'working_sessions'            => $legacy->working_sessions,
            
            // New settings fields
            'requires_approval'           => (bool)$newSettings->requires_approval,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $orgId = getPermissionsTeamId();

        $legacy = SchedulingSetting::firstOrCreate(['organization_id' => $orgId]);
        $newSettings = OrgSchedulingSettings::firstOrCreate(['organization_id' => $orgId]);

        // If new settings are passed in request, update them
        $newSettingsData = [];
        if ($request->has('requires_approval')) {
            $newSettingsData['requires_approval'] = $request->boolean('requires_approval');
        }
        if ($newSettingsData) {
            $newSettings->update($newSettingsData);
        }

        // If legacy settings are passed in request, update them
        $legacyData = [];
        if ($request->has('approval_enabled')) {
            $legacyData['approval_enabled'] = $request->boolean('approval_enabled');
        }
        if ($request->has('approval_module_types')) {
            $legacyData['approval_module_types'] = $request->input('approval_module_types');
        }
        if ($request->has('default_channels')) {
            $legacyData['default_channels'] = $request->input('default_channels');
        }
        if ($request->has('working_sessions')) {
            $legacyData['working_sessions'] = $request->input('working_sessions');
        }
        if ($legacyData) {
            $legacy->update($legacyData);
        }

        return $this->show();
    }
}
