<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\NotificationGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NotificationGroupService
{
    /**
     * Get paginated list of notification groups.
     */
    public function index(array $filters, int $limit)
    {
        return NotificationGroup::with(['creator'])
            ->withCount('users')
            ->filter($filters)
            ->paginate($limit);
    }

    /**
     * Show notification group details.
     */
    public function show(NotificationGroup $group): NotificationGroup
    {
        return $group->load(['creator', 'users']);
    }

    /**
     * Create a new notification group.
     */
    public function store(array $validated): NotificationGroup
    {
        return DB::transaction(function () use ($validated) {
            $orgId = $this->resolveCurrentOrganizationId();
            $validated['organization_id'] = $orgId;

            $userIds = $validated['user_ids'] ?? [];
            unset($validated['user_ids']);

            $group = NotificationGroup::create($validated);

            if (!empty($userIds)) {
                $group->users()->sync($userIds);
            }

            return $group->load('users');
        });
    }

    /**
     * Update an existing notification group.
     */
    public function update(NotificationGroup $group, array $validated): NotificationGroup
    {
        return DB::transaction(function () use ($group, $validated) {
            $userIds = $validated['user_ids'] ?? null;
            unset($validated['user_ids']);

            $group->update($validated);

            if ($userIds !== null) {
                $group->users()->sync($userIds);
            }

            return $group->load('users');
        });
    }

    /**
     * Delete a notification group.
     */
    public function destroy(NotificationGroup $group): bool
    {
        return DB::transaction(function () use ($group) {
            $group->users()->detach();
            return $group->delete();
        });
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
