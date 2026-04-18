<?php

namespace App\Modules\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\CbccvcLoginRequest;
use App\Modules\Auth\Requests\SsoExchangeRequest;
use App\Modules\Auth\Services\Providers\CbccvcProvider;
use App\Modules\Auth\Services\Providers\SsoDanangProvider;
use App\Modules\Auth\Services\Providers\SsoProvider;
use App\Modules\Auth\Services\UserSyncService;
use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Resources\UserResource;
use RuntimeException;

/**
 * @group Auth
 *
 * Đăng nhập qua SSO (Đà Nẵng) hoặc CBCCVC.
 */
class SsoController extends Controller
{
    public function __construct(private UserSyncService $userSyncService) {}

    /**
     * OAuth code exchange (đa provider).
     *
     * @unauthenticated
     *
     * @bodyParam provider string required Provider key. Example: sso_danang
     * @bodyParam code string required Authorization code từ SSO Gateway. Example: abc123
     *
     * @response 200 {"success": true, "data": {"access_token": "1|xxx", "token_type": "Bearer", "user": {"id": 1, "name": "..."}}}
     */
    public function exchange(SsoExchangeRequest $request)
    {
        $provider = $request->validated('provider');

        if (! $this->isProviderEnabled($provider)) {
            return $this->error('Chức năng chưa được kích hoạt.', 404);
        }

        $impl = $this->resolveProvider($provider);

        return $this->runProvider(
            fn () => $impl->getUserinfo(['code' => $request->validated('code')]),
            $provider
        );
    }

    /**
     * CBCCVC direct login (username/password).
     *
     * @unauthenticated
     *
     * @bodyParam username string required Tên đăng nhập CBCCVC. Example: giangpt
     * @bodyParam password string required Mật khẩu.
     */
    public function cbccvcLogin(CbccvcLoginRequest $request)
    {
        if (! $this->isProviderEnabled('cbccvc')) {
            return $this->error('Chức năng chưa được kích hoạt.', 404);
        }

        return $this->runProvider(
            fn () => app(CbccvcProvider::class)->getUserinfo([
                'username' => $request->validated('username'),
                'password' => $request->validated('password'),
            ]),
            'cbccvc',
            invalidCredentialsStatus: 401
        );
    }

    /**
     * Map provider key (from user_socials.provider convention) → settings `enabled` key.
     * SSO Đà Nẵng: provider='sso_danang' → setting 'sso_danang_enabled'
     * CBCCVC:      provider='cbccvc'     → setting 'sso_cbccvc_enabled'
     */
    private function isProviderEnabled(string $provider): bool
    {
        $settingKey = match ($provider) {
            'sso_danang' => 'sso_danang_enabled',
            'cbccvc' => 'sso_cbccvc_enabled',
            default => null,
        };

        return $settingKey !== null && (bool) Setting::get($settingKey, false);
    }

    private function resolveProvider(string $key): SsoProvider
    {
        return match ($key) {
            'sso_danang' => app(SsoDanangProvider::class),
            default => throw new RuntimeException("Unknown provider: {$key}"),
        };
    }

    /**
     * Wrap provider call + user sync + token issuance; chuyển exception thành HTTP response.
     */
    private function runProvider(\Closure $fetchUserinfo, string $provider, int $invalidCredentialsStatus = 400)
    {
        try {
            $userinfo = $fetchUserinfo();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'invalid credentials') || str_contains($msg, 'invalid_grant')) {
                return $this->error($msg, $invalidCredentialsStatus);
            }

            return $this->error('Cổng đăng nhập không phản hồi: '.$msg, 502);
        }

        $user = $this->userSyncService->syncFromUserinfo($provider, $userinfo);

        if ($user->status !== UserStatusEnum::Active->value) {
            return $this->forbidden('Tài khoản của bạn đã bị khóa');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ], 'Đăng nhập thành công.');
    }
}
