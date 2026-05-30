<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\NotificationGroup;

class NotificationGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.notification-groups.index');
    }

    public function view(User $user, NotificationGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.notification-groups.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.notification-groups.store');
    }

    public function update(User $user, NotificationGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.notification-groups.update');
    }

    public function delete(User $user, NotificationGroup $group): bool
    {
        return $user->hasPermissionTo('scheduling.notification-groups.destroy');
    }
}
