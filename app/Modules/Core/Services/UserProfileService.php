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

        // Profile nằm ở bảng riêng nên sửa nó không làm bẩn bảng users → event
        // 'updating' của User không chạy, updated_by/updated_at đứng im và cột
        // "Cập nhật" trên UI trống. Với người dùng thì đây vẫn là sửa hồ sơ của
        // họ, nên bump audit trên User. touch() làm model dirty → 'updating'
        // chạy → updated_by được ghi.
        $user->touch();

        return $profile->fresh();
    }
}
