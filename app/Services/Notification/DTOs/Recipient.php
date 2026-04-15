<?php

namespace App\Services\Notification\DTOs;

final readonly class Recipient
{
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $zaloId = null,
        public ?string $name = null,
    ) {}
}
