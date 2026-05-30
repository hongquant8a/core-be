<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Zalo OA Message channel — gửi tin nhắn text tự do qua Official Account (channel key: 'zalo').
 *
 * - Endpoint: POST https://openapi.zalo.me/v2.0/oa/message
 *   API legacy được mọi OA tier (kể cả nhà nước/community) chấp nhận. V3.0 endpoints
 *   (/cs, /transaction, /promotion) chỉ work cho OA verified business — đã test trả -235/-233.
 * - Auth: header `access_token` (1h). Refresh qua POST https://oauth.zaloapp.com/v4/oa/access_token
 *   (refresh_token 3 tháng, single-use → mỗi lần refresh nhận token mới phải replace).
 * - Recipient ID: Zalo user_id (từ webhook follow / OAuth). Channel đọc qua $recipient->zaloId.
 *
 * Channel ZNS (template-based qua WorldSMS) xem ZaloZnsChannel.php (key: 'zalo_zns').
 */
class ZaloChannel implements NotificationChannel
{
    private const SEND_URL = 'https://openapi.zalo.me/v2.0/oa/message';
    private const REFRESH_URL = 'https://oauth.zaloapp.com/v4/oa/access_token';
    private const REFRESH_LOCK_KEY = 'zalo:oa:refresh-token';

    public function __construct(private SettingService $settings) {}

    public function key(): string
    {
        return 'zalo';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('Kênh Zalo đang bị tắt.');
        }

        if (! $cfg['access_token']) {
            return $this->fail('Zalo not configured (missing access_token)');
        }

        $userId = $recipient->zaloId;
        if (! $userId) {
            return $this->fail('Missing Zalo user_id');
        }

        $text = trim((string) $payload->content);
        if ($text === '') {
            return $this->fail('Missing message text');
        }

        // Gửi với access_token hiện tại; nếu lỗi token + có đủ credentials thì refresh + retry 1 lần.
        $result = $this->trySend($cfg['access_token'], $userId, $text);
        if (! $result['ok'] && $result['tokenError']) {
            $canRefresh = $cfg['app_id'] && $cfg['app_secret'] && $cfg['refresh_token'];
            if (! $canRefresh) {
                return $this->fail($result['error'].' — access_token hết hạn. Cần config app_id + app_secret + refresh_token để auto-refresh, hoặc paste access_token mới.');
            }

            try {
                $newAccessToken = $this->refreshAccessToken(
                    $cfg['app_id'],
                    $cfg['app_secret'],
                    $cfg['refresh_token']
                );
            } catch (Throwable $e) {
                return $this->fail('Refresh token failed: '.$e->getMessage());
            }

            $result = $this->trySend($newAccessToken, $userId, $text);
        }

        if ($result['ok']) {
            return new SendResult(channel: 'zalo', success: true, messageId: $result['messageId']);
        }

        return $this->fail($result['error']);
    }

    /**
     * @return array{ok: bool, tokenError: bool, messageId: ?string, error: string}
     */
    private function trySend(string $accessToken, string $userId, string $text): array
    {
        try {
            $response = Http::withHeaders(['access_token' => $accessToken])
                ->acceptJson()
                ->post(self::SEND_URL, [
                    'recipient' => ['user_id' => $userId],
                    'message' => ['text' => $text],
                ]);

            $data = $response->json() ?? [];
        } catch (Throwable $e) {
            return ['ok' => false, 'tokenError' => false, 'messageId' => null, 'error' => 'HTTP error: '.$e->getMessage()];
        }

        $errorCode = (int) ($data['error'] ?? 0);

        if ($errorCode === 0) {
            return [
                'ok' => true,
                'tokenError' => false,
                'messageId' => $data['data']['message_id'] ?? null,
                'error' => '',
            ];
        }

        $message = (string) ($data['message'] ?? 'Unknown Zalo error');

        return [
            'ok' => false,
            'tokenError' => $this->isTokenError($errorCode, $message),
            'messageId' => null,
            'error' => "[{$errorCode}] {$message}",
        ];
    }

    /**
     * Refresh access_token. Single-use refresh_token → cần lock atomic chống race
     * (2 worker cùng refresh = 1 thắng, 1 mất token vĩnh viễn).
     */
    private function refreshAccessToken(string $appId, string $appSecret, string $refreshToken): string
    {
        $lock = Cache::lock(self::REFRESH_LOCK_KEY, 30);

        try {
            $lock->block(15); // chờ tối đa 15s nếu process khác đang refresh

            // Reload từ DB — process khác có thể vừa refresh xong giữa lúc chờ lock.
            $currentAccess = $this->settings->getByKey('zalo_access_token')['value'] ?? null;
            $currentRefresh = $this->settings->getByKey('zalo_refresh_token')['value'] ?? null;
            if ($currentRefresh && $currentRefresh !== $refreshToken && $currentAccess) {
                return $currentAccess;
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
                throw new \RuntimeException("Refresh response invalid: {$err}");
            }

            $this->settings->update([
                'zalo_access_token' => $newAccess,
                'zalo_refresh_token' => $newRefresh,
            ]);

            return $newAccess;
        } finally {
            $lock->release();
        }
    }

    private function isTokenError(int $code, string $message): bool
    {
        // Zalo error codes liên quan token: -124/-125 (invalid/expired access token), -216 (header), 1006 (expired).
        if (in_array($code, [-124, -125, -216, 1006], true)) {
            return true;
        }

        $msg = mb_strtolower($message);

        return str_contains($msg, 'access token')
            || str_contains($msg, 'token expired')
            || str_contains($msg, 'invalid token');
    }

    private function loadConfig(): array
    {
        return [
            'enabled' => (bool) ($this->settings->getByKey('zalo_enabled')['value'] ?? false),
            'app_id' => $this->settings->getByKey('zalo_app_id')['value'] ?? null,
            'app_secret' => $this->settings->getByKey('zalo_app_secret')['value'] ?? null,
            'access_token' => $this->settings->getByKey('zalo_access_token')['value'] ?? null,
            'refresh_token' => $this->settings->getByKey('zalo_refresh_token')['value'] ?? null,
        ];
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'zalo', success: false, error: $error);
    }
}
