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
        if ($user->hasPermissionTo('schedules.update')) {
            return true;
        }
        return $schedule->host_id === $user->id || $schedule->created_by === $user->id;
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        if ($user->hasPermissionTo('schedules.delete')) {
            return true;
        }
        return $schedule->host_id === $user->id || $schedule->created_by === $user->id;
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
        $statusVal = $schedule->status;
        if ($statusVal instanceof \App\Modules\Scheduling\Enums\ScheduleStatus) {
            $statusVal = $statusVal->value;
        }

        return ($user->hasPermissionTo('schedules.driver-view') || $user->hasRole('Lái xe') || $user->hasRole('scheduling-lai-xe')) 
            && $schedule->driver_id === $user->id
            && $statusVal === \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value;
    }
}
