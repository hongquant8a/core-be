<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\FcmToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đồng bộ FCM device token vào bảng fcm_tokens cho user hiện tại.
 *
 * Header expected:
 * - X-FCM-Token: token Firebase issue (bắt buộc để sync)
 * - X-Device-Id: UUID stable cho thiết bị (bắt buộc để identify device — FE maintain qua localStorage)
 * - X-Device-Type: android|ios|web (optional, cho format payload sau này)
 *
 * Skip nếu thiếu user (chưa auth), thiếu fcm_token, hoặc thiếu device_id.
 */
class SyncFcmToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $request->header('X-FCM-Token');
        $deviceId = $request->header('X-Device-Id');

        Log::info('SyncFcmToken middleware triggered', [
            'user_id' => $user?->id,
            'has_token' => ! empty($token),
            'has_device_id' => ! empty($deviceId),
            'token' => substr($token ?? '', 0, 30),
            'device_id' => $deviceId,
        ]);

        if ($user && $token && $deviceId) {
            DB::transaction(function () use ($user, $token, $deviceId, $request) {
                // Tránh push nhầm: nếu token này đang gắn với user khác (vd phone bán cho người khác)
                // → xóa row đó trước khi upsert.
                FcmToken::where('fcm_token', $token)
                    ->where('user_id', '!=', $user->id)
                    ->delete();

                FcmToken::updateOrCreate(
                    ['user_id' => $user->id, 'device_id' => $deviceId],
                    [
                        'fcm_token' => $token,
                        'device_type' => $request->header('X-Device-Type'),
                        'last_used_at' => now(),
                    ],
                );
            });

            Log::info('SyncFcmToken synced', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_type' => $request->header('X-Device-Type'),
            ]);
        } else {
            Log::info('SyncFcmToken skipped', [
                'reason' => ! $user ? 'no_user' : (! $token ? 'no_token' : 'no_device_id'),
            ]);
        }

        return $next($request);
    }
}
