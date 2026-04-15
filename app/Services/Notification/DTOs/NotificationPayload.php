<?php

namespace App\Services\Notification\DTOs;

final readonly class NotificationPayload
{
    public function __construct(
        public array $channels,
        public Recipient $recipient,
        public string $content,
        public ?string $subject = null,
        public array $context = [],
    ) {}
}
