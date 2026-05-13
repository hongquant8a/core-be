<?php

namespace App\Services\Zalo;

use App\Modules\Core\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Refresh access_token + refresh_token cho Zalo OA bằng app_id + app_secret + refresh_token cũ.
 *
 *  - access_token TTL: 1h  -> schedule 45p để luôn fresh (15p buffer).
 *  - refresh_token TTL: 3 tháng, SINGLE-USE -> mỗi lần refresh nhận pair mới phải replace.
 *  - Atomic lock chống race (2 worker cùng refresh -> 1 token mất vĩnh viễn).
 *  - Đọc/lưu qua SettingService (zalo_app_id / zalo_app_secret / zalo_access_token / zalo_refresh_token).
 *
 * Docs: https://developers.zalo.me/docs/official-account/api/quan-ly-token-truy-cap
 */
class ZaloOaTokenService
{
    private const REFRESH_URL = 'https://oauth.zaloapp.com/v4/oa/access_token';

    private const LOCK_KEY = 'zalo:oa:refresh-token';

    public function __construct(private SettingService $settings) {}

    /**
     * @return array{access_token: string, refresh_token: string} thông tin token mới
     *
     * @throws RuntimeException nếu missing credentials hoặc API fail
     */
    public function refresh(): array
    {
        $appId = $this->settings->getByKey('zalo_app_id')['value'] ?? null;
        $appSecret = $this->settings->getByKey('zalo_app_secret')['value'] ?? null;
        $refreshToken = $this->settings->getByKey('zalo_refresh_token')['value'] ?? null;

        if (! $appId || ! $appSecret || ! $refreshToken) {
            throw new RuntimeException('Thiếu Zalo credentials (zalo_app_id / zalo_app_secret / zalo_refresh_token) trong settings.');
        }

        $lock = Cache::lock(self::LOCK_KEY, 30);
        try {
            $lock->block(15);

            // Reload — process khác có thể vừa refresh xong.
            $latestRefresh = $this->settings->getByKey('zalo_refresh_token')['value'] ?? null;
            if ($latestRefresh && $latestRefresh !== $refreshToken) {
                $refreshToken = $latestRefresh;
            }

            $response = Http::asForm()
                ->withHeaders(['secret_key' => $appSecret])
                ->post(self::REFRESH_URL, [
                    'app_id' => $appId,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            $data = $response->json() ?? [];
            $newAccess = $data['access_token'] ?? null;
            $newRefresh = $data['refresh_token'] ?? null;

            if (! $newAccess || ! $newRefresh) {
                $err = $data['error_description'] ?? $data['error'] ?? $data['message'] ?? 'unknown';
                Log::warning('Zalo OA refresh-token failed', ['response' => $data]);
                throw new RuntimeException("Refresh Zalo token failed: {$err}");
            }

            $this->settings->update([
                'zalo_access_token' => $newAccess,
                'zalo_refresh_token' => $newRefresh,
            ]);

            return ['access_token' => $newAccess, 'refresh_token' => $newRefresh];
        } finally {
            $lock->release();
        }
    }

    public function getAccessToken(): ?string
    {
        return $this->settings->getByKey('zalo_access_token')['value'] ?? null;
    }
}
