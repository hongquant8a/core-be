<?php

namespace App\Modules\Core\Observers;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;
use Illuminate\Support\Facades\Log;

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

    /**
     * Tài khoản rời trạng thái active (nghỉ việc, bị khoá) → gỡ liên kết Telegram.
     *
     * Đây là chốt chặn bảo mật: Telegram nằm ngoài hệ thống nên khoá đăng nhập không
     * cắt được luồng tin — người đã nghỉ vẫn nhận thông báo nội bộ về máy riêng.
     * Đặt ở Observer vì trạng thái đổi qua nhiều đường: màn quản lý user, đổi hàng
     * loạt, import, seeder và tinker.
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('status') || $user->status === UserStatusEnum::Active->value) {
            return;
        }

        $profile = UserProfile::where('user_id', $user->id)->first();

        if (! $profile?->telegram_chat_id) {
            return;
        }

        $profile->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
            'telegram_token_expires_at' => null,
            'telegram_linked_at' => null,
        ]);

        Log::info('Telegram: gỡ liên kết do tài khoản đổi trạng thái.', [
            'user_id' => $user->id,
            'status' => $user->status,
        ]);
    }
}
