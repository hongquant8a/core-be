<?php

namespace App\Services\Notification\Channels;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;

class ZaloChannel implements NotificationChannel
{
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        return new SendResult(channel: 'zalo', success: false, error: 'Not implemented yet');
    }

    public function key(): string
    {
        return 'zalo';
    }
}
