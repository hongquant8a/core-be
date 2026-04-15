<?php

namespace App\Services\Notification\Contracts;

use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;

interface NotificationChannel
{
    /**
     * Send a notification. MUST return a SendResult — never throw.
     */
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult;

    /**
     * Channel registry key (e.g. 'sms', 'mail', 'zalo').
     */
    public function key(): string;
}
