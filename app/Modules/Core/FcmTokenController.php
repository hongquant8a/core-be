<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\FcmToken;
use Illuminate\Http\Request;

/**
 * @group Core - FCM Token
 *
 * Huỷ đăng ký nhận thông báo đẩy cho THIẾT BỊ đang gọi.
 *
 * Trình duyệt không cho trang web tự thu hồi quyền hiển thị thông báo — quyền đó
 * chỉ người dùng gỡ được trong Cài đặt. Thứ tắt được là việc nhận push: bỏ token
 * Firebase của thiết bị này khỏi bảng `fcm_tokens` để backend không gửi tới nữa.
 */
class FcmTokenController extends Controller
{
    /**
     * Huỷ đăng ký thiết bị hiện tại
     *
     * Xoá dòng `fcm_tokens` khớp (user đang đăng nhập, header `X-Device-Id`).
     * Các thiết bị khác của cùng người dùng giữ nguyên.
     *
     * Frontend phải xoá token khỏi bộ nhớ TRƯỚC khi gọi endpoint này. Middleware
     * `sync.fcm.token` chạy trên mọi request của nhóm và sẽ upsert lại dòng vừa
     * xoá nếu request còn kèm header `X-FCM-Token`.
     *
     * @header X-Device-Id required Mã thiết bị do frontend sinh và giữ trong localStorage. Example: 3f9c1b2a-0000-4000-8000-111122223333
     *
     * @response 200 {"success": true, "message": "Đã tắt thông báo trên thiết bị này.", "data": {"deleted": 1}}
     * @response 422 {"success": false, "message": "Thiếu header X-Device-Id nên không xác định được thiết bị."}
     */
    public function destroyMe(Request $request)
    {
        $deviceId = trim((string) $request->header('X-Device-Id'));

        // Không đoán bừa khi thiếu header: xoá theo mỗi user_id sẽ tắt thông báo
        // của TẤT CẢ thiết bị người đó đang dùng, trong khi họ chỉ định tắt máy
        // đang cầm trên tay.
        if ($deviceId === '') {
            return $this->error('Thiếu header X-Device-Id nên không xác định được thiết bị.', 422);
        }

        $deleted = FcmToken::where('user_id', $request->user()->id)
            ->where('device_id', $deviceId)
            ->delete();

        return $this->success(
            ['deleted' => $deleted],
            'Đã tắt thông báo trên thiết bị này.',
        );
    }
}
