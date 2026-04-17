<?php

namespace App\Services\Notification;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\SendResult;
use Throwable;

/**
 * Low-level channel dispatcher. Kết quả (success/error/message_id) được
 * consumer (SendDeliveryJob) ghi vào bảng notification_deliveries — không
 * cần file log nữa.
 */
class NotificationService
{
    /**
     * @param  array<string, NotificationChannel>  $channels  keyed by channel key (e.g. 'sms')
     */
    public function __construct(
        private array $channels,
    ) {}

    /**
     * @return SendResult[] one result per channel in $payload->channels, in order.
     */
    public function send(NotificationPayload $payload): array
    {
        $results = [];

        foreach ($payload->channels as $key) {
            $results[] = $this->sendOne($key, $payload);
        }

        return $results;
    }

    private function sendOne(string $key, NotificationPayload $payload): SendResult
    {
        if (! isset($this->channels[$key])) {
            return new SendResult(channel: $key, success: false, error: "Unknown channel: {$key}");
        }

        try {
            return $this->channels[$key]->send($payload->recipient, $payload);
        } catch (Throwable $e) {
            return new SendResult(channel: $key, success: false, error: 'Channel exception: '.$e->getMessage());
        }
    }
}
