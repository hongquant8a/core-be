<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\FcmChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Kreait\Firebase\Contract\Messaging as FirebaseMessaging;
use Mockery;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private const DEFAULT_SERVICE_ACCOUNT = '__default__';

    private function makeSettings(
        mixed $serviceAccount = self::DEFAULT_SERVICE_ACCOUNT,
        bool $enabled = true,
    ): SettingService {
        if ($serviceAccount === self::DEFAULT_SERVICE_ACCOUNT) {
            $serviceAccount = [
                'project_id' => 'my-project-id',
                'client_email' => 'test@my-project-id.iam.gserviceaccount.com',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvAIBADANBgkqhkiG9w0BAQEFAASC...\n-----END PRIVATE KEY-----\n",
            ];
        }

        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('firebase_service_account')->andReturn($serviceAccount === null ? null : ['value' => $serviceAccount]);
        $m->shouldReceive('getByKey')->with('fcm_enabled')->andReturn(['value' => $enabled ? '1' : '0']);

        return $m;
    }

    private function makeChannelWithMessaging(FirebaseMessaging $messaging, ?array $serviceAccount = null, bool $enabled = true): FcmChannel
    {
        $settings = $serviceAccount === null
            ? $this->makeSettings()
            : $this->makeSettings(serviceAccount: $serviceAccount, enabled: $enabled);

        return new FcmChannel($settings, $messaging);
    }

    private function send(FcmChannel $ch, ?string $fcmToken, string $content = 'hi', ?string $subject = null, array $context = []): SendResult
    {
        $recipient = new Recipient(fcmToken: $fcmToken);
        $payload = new NotificationPayload(['fcm'], $recipient, $content, subject: $subject, context: $context);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_fcm(): void
    {
        $ch = new FcmChannel($this->makeSettings());

        $this->assertSame('fcm', $ch->key());
    }

    public function test_returns_failure_when_disabled(): void
    {
        $ch = new FcmChannel($this->makeSettings(enabled: false));
        $r = $this->send($ch, 'token-1');

        $this->assertFalse($r->success);
        $this->assertSame('FCM is disabled', $r->error);
    }

    public function test_returns_failure_when_service_account_missing(): void
    {
        $ch = new FcmChannel($this->makeSettings(serviceAccount: null));
        $r = $this->send($ch, 'token-1');

        $this->assertFalse($r->success);
        $this->assertSame('FCM not configured', $r->error);
    }

    public function test_returns_failure_when_fcm_token_missing(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldNotReceive('send');

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, null);

        $this->assertFalse($r->success);
        $this->assertSame('Missing FCM device token', $r->error);
    }

    public function test_sends_correct_payload_to_fcm(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->withArgs(function ($message) {
            $payload = $message->jsonSerialize();

            return $payload['token'] === 'device-token-abc'
                && $payload['notification']['title'] === 'Title'
                && $payload['notification']['body'] === 'Hello'
                && $payload['data']['task_id'] === '5';
        })->andReturn(['name' => 'projects/my-project-id/messages/123']);

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'device-token-abc', 'Hello', 'Title', ['task_id' => 5]);

        $this->assertTrue($r->success);
        $this->assertSame('projects/my-project-id/messages/123', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_uses_default_title_when_no_subject(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->withArgs(function ($message) {
            $payload = $message->jsonSerialize();

            return $payload['notification']['title'] === 'Thông báo';
        })->andReturn(['name' => 'projects/my-project-id/messages/123']);

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'device-token-abc', 'Hello');

        $this->assertTrue($r->success);
        $this->assertNull($r->error);
    }

    public function test_omits_data_when_context_empty(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->withArgs(function ($message) {
            $payload = $message->jsonSerialize();

            return ! isset($payload['data']);
        })->andReturn(['name' => 'projects/my-project-id/messages/123']);

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'device-token-abc', 'Hello');

        $this->assertTrue($r->success);
        $this->assertNull($r->error);
    }

    public function test_success_when_fcm_returns_success_1(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->andReturn(['name' => 'projects/my-project-id/messages/msg-abc']);

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'device-token');

        $this->assertTrue($r->success);
        $this->assertSame('projects/my-project-id/messages/msg-abc', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_when_fcm_returns_success_0(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->andThrow(new \RuntimeException('InvalidRegistration'));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'bad-token');

        $this->assertFalse($r->success);
        $this->assertSame('FCM send failed: InvalidRegistration', $r->error);
    }

    public function test_catches_http_exception(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('send')->andThrow(new \RuntimeException('Network down'));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, 'device-token');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Network down', $r->error);
    }
}
