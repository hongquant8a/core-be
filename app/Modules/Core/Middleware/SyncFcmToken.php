<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\FcmToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đồng bộ FCM device token vào bảng fcm_tokens cho user hiện tại.
 *
 * Header expected:
 * - X-FCM-Token: token Firebase issue (bắt buộc để sync)
 * - X-Device-Id: UUID stable cho thiết bị (bắt buộc để identify device — FE maintain qua localStorage)
 * - X-Device-Type: android|ios|web|pwa (optional, cho format payload sau này)
 *
 * Skip nếu thiếu user (chưa auth), thiếu fcm_token, hoặc thiếu device_id.
 */
class SyncFcmToken
{
    /** Giới hạn cột của bảng fcm_tokens. Header dài hơn thì bỏ qua, xem handle(). */
    private const MAX_TOKEN_LENGTH = 512;

    private const MAX_DEVICE_ID_LENGTH = 100;

    private const MAX_DEVICE_TYPE_LENGTH = 20;

    /** Token không đổi thì ghi lại nhiều nhất một lần trong khoảng này. */
    private const THROTTLE_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = trim((string) $request->header('X-FCM-Token'));
        $deviceId = trim((string) $request->header('X-Device-Id'));

        if (! $user || $token === '' || $deviceId === '') {
            return $next($request);
        }

        // Header do client tự sinh nên phải tự kiểm độ dài: chuỗi vượt kích thước
        // cột làm MySQL (strict mode) ném SQLSTATE 22001, và vì middleware này
        // chạy trên MỌI route đã đăng nhập nên lỗi đó biến thành 500 cho toàn bộ
        // API của client đó chứ không riêng phần thông báo.
        if (mb_strlen($token) > self::MAX_TOKEN_LENGTH || mb_strlen($deviceId) > self::MAX_DEVICE_ID_LENGTH) {
            return $next($request);
        }

        $deviceType = trim((string) $request->header('X-Device-Type')) ?: null;
        if ($deviceType !== null && mb_strlen($deviceType) > self::MAX_DEVICE_TYPE_LENGTH) {
            $deviceType = null;
        }

        // Middleware chạy trên mọi request, mà mỗi lần ghi là một transaction kèm
        // vài câu lệnh — một màn hình gọi chục API là chục lần ghi cho cùng một
        // dòng không đổi. Chỉ ghi thật khi token/loại thiết bị đổi, hoặc khi dấu
        // vết throttle đã hết hạn (để last_used_at còn phản ánh được máy còn dùng).
        $cacheKey = self::cacheKey($user->id, $deviceId);
        $fingerprint = sha1($token.'|'.$deviceType);

        if (Cache::get($cacheKey) === $fingerprint) {
            return $next($request);
        }

        $this->sync($user->id, $deviceId, $token, $deviceType);

        Cache::put($cacheKey, $fingerprint, now()->addMinutes(self::THROTTLE_MINUTES));

        return $next($request);
    }

    private function sync(int $userId, string $deviceId, string $token, ?string $deviceType): void
    {
        DB::transaction(function () use ($userId, $deviceId, $token, $deviceType) {
            // Một token Firebase chỉ được nằm ở đúng một dòng:
            // - user khác: máy đổi chủ (bán/cho mượn) → không được đẩy nhầm.
            // - cùng user nhưng device_id khác: người dùng xoá dữ liệu trang nên
            //   sinh device_id mới trong khi service worker vẫn giữ token cũ. Để
            //   nguyên thì cùng một token nằm ở hai dòng, sendMulticast nhận danh
            //   sách có phần tử lặp và máy hiện hai thông báo giống hệt nhau.
            FcmToken::where('fcm_token', $token)
                ->where(function ($query) use ($userId, $deviceId) {
                    $query->where('user_id', '!=', $userId)
                        ->orWhere('device_id', '!=', $deviceId);
                })
                ->delete();

            // upsert() đẩy xuống INSERT ... ON DUPLICATE KEY UPDATE nên an toàn khi
            // nhiều request song song cùng đăng ký lần đầu. updateOrCreate là SELECT
            // rồi INSERT, hai request chen nhau ở khe giữa sẽ ném lỗi trùng khoá
            // unique(user_id, device_id) → 500 đúng lúc thiết bị vừa lấy được token.
            FcmToken::upsert(
                [[
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'fcm_token' => $token,
                    'device_type' => $deviceType,
                    'last_used_at' => now(),
                ]],
                ['user_id', 'device_id'],
                ['fcm_token', 'device_type', 'last_used_at'],
            );
        });
    }

    public static function cacheKey(int $userId, string $deviceId): string
    {
        return 'fcm:sync:'.$userId.':'.sha1($deviceId);
    }

    /**
     * Xoá dấu vết throttle của một thiết bị.
     *
     * Bắt buộc gọi ở mọi chỗ xoá dòng fcm_tokens (tắt thông báo, đăng xuất): còn
     * dấu vết thì request kế tiếp tưởng "không có gì đổi" và bỏ qua việc ghi, nên
     * nếu Firebase cấp lại đúng token cũ thì thiết bị không được đăng ký lại.
     */
    public static function forget(int $userId, string $deviceId): void
    {
        Cache::forget(self::cacheKey($userId, $deviceId));
    }
}
