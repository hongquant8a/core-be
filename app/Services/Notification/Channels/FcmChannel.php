<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class FcmChannel implements NotificationChannel
{
    private ?string $url;

    private ?string $serverKey;

    private bool $enabled;

    public function __construct(SettingService $settings)
    {
        $this->url = $settings->getByKey('api_firebase_url')['value'] ?? null;
        $this->serverKey = $settings->getByKey('api_firebase_token')['value'] ?? null;
        $this->enabled = (bool) ($settings->getByKey('api_firebase_enabled')['value'] ?? false);
    }

    public function key(): string
    {
        return 'fcm';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        if (! $this->enabled) {
            return $this->fail('FCM is disabled');
        }

        if (! $this->url || ! $this->serverKey) {
            return $this->fail('FCM not configured');
        }

        if (! $recipient->fcmToken) {
            return $this->fail('Missing FCM device token');
        }

        $body = [
            'to' => $recipient->fcmToken,
            'notification' => [
                'title' => $payload->subject ?: 'Thông báo',
                'body' => $payload->content,
            ],
        ];

        if (! empty($payload->context)) {
            $body['data'] = $payload->context;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$this->serverKey,
            ])
                ->acceptJson()
                ->post($this->url, $body);

            $data = $response->json();
        } catch (Throwable $e) {
            return $this->fail('HTTP error: '.$e->getMessage());
        }

        $success = ($data['success'] ?? 0) >= 1;

        if ($success) {
            return new SendResult(
                channel: 'fcm',
                success: true,
                messageId: $data['results'][0]['message_id'] ?? ($data['message_id'] ?? null),
            );
        }

        $error = $data['results'][0]['error'] ?? ($data['error'] ?? 'FCM send failed');

        return $this->fail($error);
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'fcm', success: false, error: $error);
    }
}
