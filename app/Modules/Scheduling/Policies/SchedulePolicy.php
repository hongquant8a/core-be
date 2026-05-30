<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;

class SchedulePolicy
{
    /**
     * Determine whether the user can view any schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.index');
    }

    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        if (!$user->hasPermissionTo('scheduling.schedules.show')) {
            return false;
        }

        // Driver restriction
        if ($user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            return (int) $schedule->driver_id === (int) $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create schedules.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.store');
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        if (!$user->hasPermissionTo('scheduling.schedules.update')) {
            return false;
        }

        // Admins and scheduling coordinators can update any schedule
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'])) {
            return true;
        }

        // Secretaries and staff can only update their own schedules
        return (int) $schedule->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the schedule.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        if (!$user->hasPermissionTo('scheduling.schedules.destroy')) {
            return false;
        }

        // Admins and scheduling coordinators can delete any schedule
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch'])) {
            return true;
        }

        // Others can only delete their own schedules
        return (int) $schedule->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can approve the schedule.
     */
    public function approve(User $user, Schedule $schedule): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.approve');
    }

    /**
     * Determine whether the user can reorder schedules.
     */
    public function reorder(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.reorder');
    }

    /**
     * Determine whether the user can export schedules.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.export');
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function stats(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.stats');
    }

    /**
     * Determine whether the user can bulk update status of schedules.
     */
    public function updateStatus(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.schedules.bulkUpdateStatus');
    }
}
