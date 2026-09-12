<?php

namespace App\Services\Notification\DTOs;

final readonly class SendResult
{
    /**
     * @param  bool  $permanent  Lỗi vĩnh viễn — gửi lại vẫn hỏng vì địa chỉ nhận không còn dùng được
     *                           (user chặn bot Telegram, chat không tồn tại). Consumer dùng cờ này để
     *                           dọn thông tin liên hệ hỏng thay vì retry vô ích. Lỗi nội dung/cấu hình
     *                           KHÔNG tính là vĩnh viễn.
     */
    public function __construct(
        public string $channel,
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
        public bool $permanent = false,
    ) {}
}
