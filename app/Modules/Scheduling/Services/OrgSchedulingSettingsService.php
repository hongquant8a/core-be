<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\OrgSchedulingSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrgSchedulingSettingsService
{
    /**
     * Get settings for the current organization.
     */
    public function show(): OrgSchedulingSettings
    {
        return $this->resolveForCurrentOrg()->load(['editor']);
    }

    /**
     * Update settings for the current organization.
     */
    public function update(array $validated): OrgSchedulingSettings
    {
        $settings = $this->resolveForCurrentOrg();
        $settings->update($validated);

        return $settings->load(['editor']);
    }

    /**
     * Resolve the settings record for the current organization.
     */
    protected function resolveForCurrentOrg(): OrgSchedulingSettings
    {
        $orgId = $this->resolveCurrentOrganizationId();

        return OrgSchedulingSettings::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'executive_approval_required' => false,
                'office_approval_required' => false,
                'executive_approver_roles' => ['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'],
                'office_approver_roles' => ['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'],
            ]
        );
    }

    /**
     * Resolve the current organization ID.
     */
    protected function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (!is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
