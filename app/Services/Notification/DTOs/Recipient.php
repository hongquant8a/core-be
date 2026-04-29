<?php

namespace App\Services\Notification\DTOs;

final readonly class Recipient
{
    /**
     * @param  array<string>|null  $fcmTokens  Multi-device push: list FCM token cho FcmChannel multicast.
     *                                          Null = no FCM. Khi có, FcmChannel ưu tiên dùng array thay vì $fcmToken.
     */
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $zaloId = null,
        public ?string $name = null,
        public ?string $fcmToken = null,
        public ?array $fcmTokens = null,
    ) {}
}
