<?php

namespace App\Services\Notification;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\SendResult;
use Psr\Log\LoggerInterface;
use Throwable;

class NotificationService
{
    /**
     * @param  array<string, NotificationChannel>  $channels  keyed by channel key (e.g. 'sms')
     */
    public function __construct(
        private array $channels,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return SendResult[]  one result per channel in $payload->channels, in order.
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
            $result = new SendResult(channel: $key, success: false, error: "Unknown channel: {$key}");
            $this->log($result, $payload);

            return $result;
        }

        try {
            $result = $this->channels[$key]->send($payload->recipient, $payload);
        } catch (Throwable $e) {
            $result = new SendResult(channel: $key, success: false, error: 'Channel exception: '.$e->getMessage());
        }

        $this->log($result, $payload);

        return $result;
    }

    private function log(SendResult $result, NotificationPayload $payload): void
    {
        $context = [
            'channel' => $result->channel,
            'recipient' => [
                'phone'    => $payload->recipient->phone,
                'email'    => $payload->recipient->email,
                'zalo_id'  => $payload->recipient->zaloId,
                'name'     => $payload->recipient->name,
            ],
            'content_preview'  => substr($payload->content, 0, 100),
            'business_context' => $payload->context,
        ];

        if ($result->success) {
            $context['message_id'] = $result->messageId;
            $this->logger->info('notification.sent', $context);
        } else {
            $context['error'] = $result->error;
            $this->logger->warning('notification.failed', $context);
        }
    }
}
