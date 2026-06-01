<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulePolicy
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
        return $user->hasPermissionTo('schedules.view');
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('schedules.create');
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.update');
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('schedules.delete');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo('schedules.update');
    }

    public function approve(User $user, Schedule $schedule): bool
    {
        return $user->hasPermissionTo('schedules.approve');
    }

    public function driverViewAny(User $user): bool
    {
        return $user->hasPermissionTo('schedules.driver-view') || $user->hasRole('Lái xe') || $user->hasRole('scheduling-lai-xe');
    }

    public function driverView(User $user, Schedule $schedule): bool
    {
        return ($user->hasPermissionTo('schedules.driver-view') || $user->hasRole('Lái xe') || $user->hasRole('scheduling-lai-xe')) 
            && $schedule->driver_user_id === $user->id
            && $schedule->status === \App\Modules\Scheduling\Enums\ScheduleStatusEnum::Approved->value;
    }
}
