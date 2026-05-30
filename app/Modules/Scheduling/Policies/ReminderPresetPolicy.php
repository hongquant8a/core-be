<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\ReminderPreset;

class ReminderPresetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.reminder-presets.index');
    }

    public function view(User $user, ReminderPreset $preset): bool
    {
        return $user->hasPermissionTo('scheduling.reminder-presets.show');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scheduling.reminder-presets.store');
    }

    public function update(User $user, ReminderPreset $preset): bool
    {
        return $user->hasPermissionTo('scheduling.reminder-presets.update');
    }

    public function delete(User $user, ReminderPreset $preset): bool
    {
        return $user->hasPermissionTo('scheduling.reminder-presets.destroy');
    }
}
