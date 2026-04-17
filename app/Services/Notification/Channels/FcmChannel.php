<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Kreait\Firebase\Contract\Messaging as FirebaseMessaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

class FcmChannel implements NotificationChannel
{
    private ?FirebaseMessaging $messaging = null;

    private bool $messagingInitAttempted = false;

    private bool $enabled;

    private array $serviceAccount = [];

    private ?string $projectId = null;

    public function __construct(SettingService $settings, ?FirebaseMessaging $messaging = null)
    {
        $account = $settings->getByKey('firebase_service_account')['value'] ?? null;
        $this->serviceAccount = is_array($account) ? $account : (is_string($account) ? json_decode($account, true) ?? [] : []);
        $this->projectId = $this->serviceAccount['project_id'] ?? null;
        $this->enabled = (bool) ($settings->getByKey('fcm_enabled')['value'] ?? false);
        $this->messaging = $messaging;
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

        if (! $this->messaging && ! $this->messagingInitAttempted) {
            $this->messaging = $this->createMessaging();
            $this->messagingInitAttempted = true;
        }

        if (! $this->messaging) {
            return $this->fail('FCM not configured');
        }

        if (! $recipient->fcmToken) {
            return $this->fail('Missing FCM device token');
        }

        $message = CloudMessage::new()
            ->withToken($recipient->fcmToken)
            ->withNotification(FirebaseNotification::create($payload->subject ?: 'Thông báo', $payload->content));

        if (! empty($payload->context)) {
            $message = $message->withData(array_map(
                fn ($value) => is_string($value) ? $value : json_encode($value),
                $payload->context,
            ));
        }

        try {
            $response = $this->messaging->send($message);
        } catch (Throwable $e) {
            return $this->fail('FCM send failed: '.$e->getMessage());
        }

        return new SendResult(
            channel: 'fcm',
            success: true,
            messageId: $response['name'] ?? null,
        );
    }

    protected function createMessaging(): ?FirebaseMessaging
    {
        if (! $this->projectId || empty($this->serviceAccount['private_key']) || empty($this->serviceAccount['client_email'])) {
            return null;
        }

        try {
            return (new Factory)
                ->withServiceAccount($this->serviceAccount)
                ->createMessaging();
        } catch (Throwable) {
            return null;
        }
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'fcm', success: false, error: $error);
    }
}
