<?php

namespace App\Modules\Scheduling\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingFilterPreset;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchedulingFilterPresetPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return true;
        }
        return null;
    }

    public function update(User $user, SchedulingFilterPreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }

    public function delete(User $user, SchedulingFilterPreset $preset): bool
    {
        return $preset->user_id === $user->id;
    }
}
