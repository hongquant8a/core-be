<?php

namespace App\Services\Notification\DTOs;

final readonly class SendResult
{
    public function __construct(
        public string $channel,
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}
}
