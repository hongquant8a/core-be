<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;

class SchedulingEmployeeGroupPolicy
{
    /**
     * Determine whether the user can view any scheduling employee groups.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.index');
    }

    /**
     * Determine whether the user can view the scheduling employee group.
     */
    public function view(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.show');
    }

    /**
     * Determine whether the user can create scheduling employee groups.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.store');
    }

    /**
     * Determine whether the user can update the scheduling employee group.
     */
    public function update(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.update');
    }

    /**
     * Determine whether the user can delete the scheduling employee group.
     */
    public function delete(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.destroy');
    }

    /**
     * Determine whether the user can change status of scheduling employee group.
     */
    public function changeStatus(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.changeStatus');
    }

    /**
     * Determine whether the user can bulk update status of scheduling employee groups.
     */
    public function bulkUpdateStatus(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.bulkUpdateStatus');
    }

    /**
     * Determine whether the user can bulk destroy scheduling employee groups.
     */
    public function bulkDestroy(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employee-groups.bulkDestroy');
    }
}
