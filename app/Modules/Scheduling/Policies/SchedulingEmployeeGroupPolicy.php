<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulingEmployeeGroupPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.view');
    }

    public function view(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.create');
    }

    public function update(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.update');
    }

    public function delete(User $user, SchedulingEmployeeGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.delete');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employee-groups.update');
    }
}
