<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulingSettingPolicy
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
        return $user->hasPermissionTo('schedules.view') || $user->hasPermissionTo('scheduling-settings.update');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling-settings.update');
    }
}
