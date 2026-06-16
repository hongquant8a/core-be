<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\SendTestNotificationRequest;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\NotificationService;

/**
 * @group Core - Notification
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifier) {}

    /**
     * Gửi thông báo kiểm thử qua kênh được chọn.
     *
     * Endpoint nội bộ để admin kiểm tra cấu hình SMS/Mail/Zalo. Trả về `SendResult[]`
     * — mỗi phần tử gồm `channel`, `success`, `message_id?`, `error?`.
     */
    public function test(SendTestNotificationRequest $request)
    {
        $data = $request->validated();

        $payload = new NotificationPayload(
            channels: $data['channels'],
            recipient: new Recipient(
                phone: $data['phone'] ?? null,
                email: $data['email'] ?? null,
                zaloId: $data['zalo_id'] ?? null,
                name: $data['name'] ?? null,
                fcmToken: $data['fcm_token'] ?? null,
                telegramChatId: $data['telegram_chat_id'] ?? null,
            ),
            content: $data['content'],
            subject: $data['subject'] ?? null,
            context: $data['context'] ?? [],
        );

        $results = $this->notifier->send($payload);

        return $this->success(
            array_map(fn ($r) => [
                'channel' => $r->channel,
                'success' => $r->success,
                'message_id' => $r->messageId,
                'error' => $r->error,
            ], $results),
            'Gửi thông báo kiểm thử hoàn tất',
        );
    }
}
