<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSyncService
{
    /**
     * Đồng bộ user từ userinfo của provider:
     * 1. Match user_socials theo (provider, provider_user_id) → load user, refresh provider_data.
     * 2. Không match → lookup users theo email.
     *    a. Có → link social vào user đó (không update user fields).
     *    b. Không có → tạo user mới + gán role default + link social.
     *
     * @param  array{email: string, name: string, sub: string, raw: array}  $userinfo
     */
    public function syncFromUserinfo(string $provider, array $userinfo): User
    {
        $providerUserId = trim((string) $userinfo['sub']);
        $email = trim((string) $userinfo['email']);
        $name = trim((string) $userinfo['name']);

        return DB::transaction(function () use ($provider, $providerUserId, $email, $name, $userinfo) {
            $social = UserSocial::where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($social) {
                $social->update(['provider_data' => $userinfo['raw'] ?? $userinfo]);

                return $social->user;
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'user_name' => null,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                ]);

                if ($roleId = Setting::get('auth_auto_create_default_role_id')) {
                    $role = \Spatie\Permission\Models\Role::find($roleId);
                    if ($role) {
                        $user->assignRole($role);
                    }
                }
            }

            UserSocial::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'provider_data' => $userinfo['raw'] ?? $userinfo,
                'linked_at' => now(),
            ]);

            return $user;
        });
    }
}
