<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;

class SchedulingEmployeePolicy
{
    /**
     * Determine whether the user can view any scheduling employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employees.index');
    }

    /**
     * Determine whether the user can view the scheduling employee.
     */
    public function view(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling.employees.show');
    }

    /**
     * Determine whether the user can create scheduling employees.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employees.store');
    }

    /**
     * Determine whether the user can update the scheduling employee.
     */
    public function update(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling.employees.update');
    }

    /**
     * Determine whether the user can delete the scheduling employee.
     */
    public function delete(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling.employees.destroy');
    }

    /**
     * Determine whether the user can change status of scheduling employee.
     */
    public function changeStatus(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employees.changeStatus');
    }

    /**
     * Determine whether the user can bulk update status of scheduling employees.
     */
    public function bulkUpdateStatus(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employees.bulkUpdateStatus');
    }

    /**
     * Determine whether the user can bulk destroy scheduling employees.
     */
    public function bulkDestroy(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.employees.bulkDestroy');
    }
}
