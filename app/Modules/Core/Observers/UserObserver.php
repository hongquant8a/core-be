<?php

namespace App\Modules\Core\Observers;

use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;

class UserObserver
{
    /**
     * Auto-create empty UserProfile khi 1 user mới được tạo.
     * Dùng firstOrCreate để idempotent (vd nếu code thủ công đã tạo trước).
     */
    public function created(User $user): void
    {
        UserProfile::firstOrCreate(['user_id' => $user->id]);
    }
}
