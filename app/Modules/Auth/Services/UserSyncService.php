<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Models\User;
use RuntimeException;

class UserSyncService
{
    /**
     * Tìm user local khớp với thông tin từ SSO provider.
     *
     * Match bằng email trước, fallback sang user_name.
     * Không tự động tạo user — nếu không match được thì throw.
     *
     * @param  string  $email     Email từ provider userinfo
     * @param  string  $username  Username từ provider (sub hoặc input)
     *
     * @throws RuntimeException Khi không tìm thấy user local nào khớp.
     */
    public function matchLocalUser(string $email, string $username): User
    {
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        if ($username) {
            $user = User::where('user_name', $username)->first();
            if ($user) {
                return $user;
            }
        }

        throw new RuntimeException('Không tìm thấy tài khoản local khớp với tài khoản SSO.');
    }
}
