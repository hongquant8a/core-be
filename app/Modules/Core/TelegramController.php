<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Jobs\ProcessTelegramUpdateJob;
use App\Modules\Core\Services\SettingService;
use App\Modules\Core\Services\TelegramLinkService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * @group Core - Telegram
 *
 * Liên kết tài khoản nhân viên với Telegram để nhận thông báo công việc.
 *
 * Telegram là kênh phụ: thông báo luôn được ghi trong ứng dụng, chưa liên kết thì
 * vẫn xem đủ khi mở hệ thống. Nhân viên tự liên kết bằng deep link, không cần admin.
 */
class TelegramController extends Controller
{
    public function __construct(private TelegramLinkService $linkService) {}

    /**
     * Trạng thái liên kết Telegram của tài khoản đang đăng nhập
     *
     * Frontend dùng endpoint này để hiện khối "Thông báo Telegram" và để hỏi lại
     * trạng thái trong lúc người dùng đang quét mã QR.
     *
     * @response 200 {"success": true, "data": {"linked": true, "linked_at": "09:30:00 12/09/2026", "pending_link": false, "expires_at": null, "bot_username": "danatec_qlcv_bot"}}
     */
    public function status(Request $request)
    {
        return $this->success($this->linkService->status($request->user()));
    }

    /**
     * Tạo đường dẫn liên kết Telegram
     *
     * Sinh token dùng một lần (hạn 24 giờ) và trả về deep link để bấm hoặc dựng mã QR.
     * Mỗi lần gọi vô hiệu hoá đường dẫn đã tạo trước đó.
     *
     * @response 200 {"success": true, "message": "Đã tạo đường dẫn liên kết.", "data": {"deep_link": "https://t.me/danatec_qlcv_bot?start=abc", "expires_at": "09:30:00 13/09/2026"}}
     * @response 422 {"success": false, "message": "Chưa cấu hình bot Telegram. Liên hệ quản trị hệ thống."}
     */
    public function link(Request $request)
    {
        try {
            $data = $this->linkService->createLink($request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($data, 'Đã tạo đường dẫn liên kết.');
    }

    /**
     * Hủy liên kết Telegram của tài khoản đang đăng nhập
     *
     * @response 200 {"success": true, "message": "Đã hủy liên kết Telegram."}
     */
    public function unlink(Request $request)
    {
        $this->linkService->unlink($request->user());

        return $this->success(null, 'Đã hủy liên kết Telegram.');
    }

    /**
     * Gửi tin nhắn thử tới Telegram của tài khoản đang đăng nhập
     *
     * @response 200 {"success": true, "message": "Đã gửi tin nhắn thử."}
     * @response 422 {"success": false, "message": "Tài khoản chưa liên kết Telegram."}
     */
    public function test(Request $request)
    {
        try {
            $result = $this->linkService->sendTest($request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        if (! $result->success) {
            return $this->error($result->error ?: 'Gửi tin nhắn thử thất bại.', 422);
        }

        return $this->success(null, 'Đã gửi tin nhắn thử.');
    }

    /**
     * Webhook nhận update từ Telegram
     *
     * Endpoint công khai, xác thực bằng header bí mật do Telegram gửi kèm (cấu hình
     * `tg_webhook_secret`). Luôn trả 200 thật nhanh rồi xử lý trong hàng đợi — chậm
     * quá 60 giây là Telegram gửi lại update, người dùng nhận tin nhắn trùng.
     *
     * @unauthenticated
     *
     * @header X-Telegram-Bot-Api-Secret-Token required Chuỗi bí mật đã đăng ký với Telegram khi đặt webhook.
     *
     * @response 200 {"success": true, "message": "OK"}
     * @response 403 {"success": false, "message": "Chữ ký webhook không hợp lệ."}
     */
    public function webhook(Request $request, SettingService $settings)
    {
        $secret = $settings->getByKey('tg_webhook_secret')['value'] ?? null;

        // Chưa cấu hình secret thì từ chối tất: mở cửa cho mọi POST ẩn danh còn tệ hơn
        // là webhook chưa chạy, vì ai cũng giả được lệnh liên kết tài khoản.
        if (! $secret || ! hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return $this->error('Chữ ký webhook không hợp lệ.', 403);
        }

        ProcessTelegramUpdateJob::dispatch($request->all())->onQueue('notifications');

        return $this->success(null, 'OK');
    }
}
