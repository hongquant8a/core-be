<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;

class UserProfileService
{
    public function show(User $user): UserProfile
    {
        return UserProfile::firstOrCreate(['user_id' => $user->id]);
    }

    public function update(User $user, array $validated): UserProfile
    {
        $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->update($validated);

        return $profile->fresh();
    }
}
