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
    private ?FirebaseMessaging $cachedMessaging = null;

    private ?string $cachedAccountHash = null;

    public function __construct(
        private SettingService $settings,
        private ?FirebaseMessaging $injectedMessaging = null,
    ) {}

    public function key(): string
    {
        return 'fcm';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('FCM is disabled');
        }

        $messaging = $this->resolveMessaging($cfg['service_account']);
        if (! $messaging) {
            return $this->fail('FCM not configured');
        }

        // Lấy danh sách tokens — ưu tiên array (multi-device), fallback single token (BC).
        $tokens = $recipient->fcmTokens ?? ($recipient->fcmToken ? [$recipient->fcmToken] : []);
        if (empty($tokens)) {
            return $this->fail('Missing FCM device token');
        }

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($payload->subject ?: 'Thông báo', $payload->content));

        if (! empty($payload->context)) {
            $message = $message->withData(array_map(
                fn ($value) => is_string($value) ? $value : json_encode($value),
                $payload->context,
            ));
        }

        try {
            $report = $messaging->sendMulticast($message, array_values($tokens));
        } catch (Throwable $e) {
            return $this->fail('FCM send failed: '.$e->getMessage());
        }

        // Cleanup tokens BE biết là không còn dùng được:
        // - messageTargetWasInvalid: token sai format (Firebase reject ngay)
        // - messageWasSentToUnknownToken: token format OK nhưng device unregister (app uninstall)
        $tokensToDelete = [];
        foreach ($report->failures()->getItems() as $failure) {
            if ($failure->messageTargetWasInvalid() || $failure->messageWasSentToUnknownToken()) {
                $tokensToDelete[] = $failure->target()->value();
            }
        }
        if (! empty($tokensToDelete)) {
            \App\Modules\Core\Models\FcmToken::whereIn('fcm_token', $tokensToDelete)->delete();
        }

        $successCount = $report->successes()->count();
        if ($successCount === 0) {
            return $this->fail('FCM multicast: all '.count($tokens).' tokens failed');
        }

        return new SendResult(
            channel: 'fcm',
            success: true,
            messageId: 'multicast:'.$successCount.'/'.count($tokens),
        );
    }

    private function loadConfig(): array
    {
        $account = $this->settings->getByKey('firebase_service_account')['value'] ?? null;
        $serviceAccount = is_array($account) ? $account : (is_string($account) ? json_decode($account, true) ?? [] : []);

        return [
            'enabled' => (bool) ($this->settings->getByKey('fcm_enabled')['value'] ?? false),
            'service_account' => $serviceAccount,
        ];
    }

    private function resolveMessaging(array $serviceAccount): ?FirebaseMessaging
    {
        if ($this->injectedMessaging) {
            return $this->injectedMessaging;
        }

        if (empty($serviceAccount['project_id']) || empty($serviceAccount['private_key']) || empty($serviceAccount['client_email'])) {
            return null;
        }

        // Cache messaging client by service account hash — rebuild chỉ khi admin đổi credentials.
        $hash = md5(serialize($serviceAccount));
        if ($this->cachedMessaging && $this->cachedAccountHash === $hash) {
            return $this->cachedMessaging;
        }

        try {
            $messaging = (new Factory)
                ->withServiceAccount($serviceAccount)
                ->createMessaging();
        } catch (Throwable) {
            return null;
        }

        $this->cachedMessaging = $messaging;
        $this->cachedAccountHash = $hash;

        return $messaging;
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'fcm', success: false, error: $error);
    }
}
