<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulingEmployeePolicy
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
        return $user->hasPermissionTo('scheduling-employees.view');
    }

    public function view(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling-employees.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employees.create');
    }

    public function update(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling-employees.update');
    }

    public function delete(User $user, SchedulingEmployee $employee): bool
    {
        return $user->hasPermissionTo('scheduling-employees.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employees.delete');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-employees.update');
    }
}
