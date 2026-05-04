<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\FcmChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging as FirebaseMessaging;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use RefreshDatabase;

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

    /**
     * Build real MulticastSendReport — class final không mock được, dùng SendReport::success / ::failure.
     *
     * @param  array<string>  $successTokens  tokens trả OK
     * @param  array<string>  $invalidTokens  tokens trả NotFound/Invalid (sẽ bị xóa khỏi DB)
     */
    private function makeReport(array $successTokens, array $invalidTokens = []): MulticastSendReport
    {
        $items = [];
        foreach ($successTokens as $t) {
            $items[] = SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $t), ['name' => 'msg-'.$t]);
        }
        foreach ($invalidTokens as $t) {
            $err = new \Kreait\Firebase\Exception\Messaging\NotFound('Requested entity was not found.');
            $items[] = SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, $t), $err);
        }

        return MulticastSendReport::withItems($items);
    }

    private function send(FcmChannel $ch, ?array $tokens, string $content = 'hi', ?string $subject = null, array $context = []): SendResult
    {
        $recipient = $tokens ? new Recipient(fcmTokens: $tokens) : new Recipient;
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
        $r = $this->send($ch, ['token-1']);

        $this->assertFalse($r->success);
        $this->assertSame('FCM is disabled', $r->error);
    }

    public function test_returns_failure_when_service_account_missing(): void
    {
        $ch = new FcmChannel($this->makeSettings(serviceAccount: null));
        $r = $this->send($ch, ['token-1']);

        $this->assertFalse($r->success);
        $this->assertSame('FCM not configured', $r->error);
    }

    public function test_returns_failure_when_no_tokens(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, null);

        $this->assertFalse($r->success);
        $this->assertSame('Missing FCM device token', $r->error);
    }

    public function test_sends_multicast_with_correct_payload(): void
    {
        $capturedMessage = null;
        $capturedTokens = null;
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->andReturnUsing(function ($message, $tokens) use (&$capturedMessage, &$capturedTokens) {
            $capturedMessage = $message;
            $capturedTokens = $tokens;

            return $this->makeReport(['device-token-abc']);
        });

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['device-token-abc'], 'Hello', 'Title', ['task_id' => 5]);

        $this->assertTrue($r->success, 'r->error = '.($r->error ?? 'null').' / capturedTokens='.json_encode($capturedTokens));
        $this->assertSame('Đã gửi 1 thiết bị', $r->messageId);
        $this->assertNull($r->error);
        $this->assertSame(['device-token-abc'], $capturedTokens);
        $payload = $capturedMessage->jsonSerialize();
        $this->assertSame('Title', $payload['notification']['title']);
        $this->assertSame('Hello', $payload['notification']['body']);
        $this->assertSame('5', $payload['data']['task_id']);
    }

    public function test_uses_default_title_when_no_subject(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->withArgs(function ($message, $tokens) {
            $payload = $message->jsonSerialize();

            return $payload['notification']['title'] === 'Thông báo';
        })->andReturn($this->makeReport(['device-token-abc']));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['device-token-abc'], 'Hello');

        $this->assertTrue($r->success);
    }

    public function test_omits_data_when_context_empty(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->withArgs(function ($message, $tokens) {
            $payload = $message->jsonSerialize();

            return ! isset($payload['data']);
        })->andReturn($this->makeReport(['device-token-abc']));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['device-token-abc'], 'Hello');

        $this->assertTrue($r->success);
    }

    public function test_partial_success_when_some_tokens_fail(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->andReturn($this->makeReport(['token-1', 'token-2'], ['bad-token-1']));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['token-1', 'token-2', 'bad-token-1']);

        $this->assertTrue($r->success);
        $this->assertSame('Đã gửi 2/3 thiết bị', $r->messageId);
    }

    public function test_full_failure_when_all_tokens_invalid(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->andReturn($this->makeReport([], ['bad-1', 'bad-2']));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['bad-1', 'bad-2']);

        $this->assertFalse($r->success);
        $this->assertStringContainsString('all 2 tokens failed', $r->error);
    }

    public function test_catches_http_exception(): void
    {
        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->andThrow(new \RuntimeException('Network down'));

        $ch = $this->makeChannelWithMessaging($messaging);
        $r = $this->send($ch, ['device-token']);

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Network down', $r->error);
    }

    public function test_invalid_tokens_are_deleted_from_db(): void
    {
        $user = \App\Modules\Core\Models\User::factory()->create();
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'd1', 'fcm_token' => 'good', 'last_used_at' => now(),
        ]);
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $user->id, 'device_id' => 'd2', 'fcm_token' => 'bad', 'last_used_at' => now(),
        ]);

        $messaging = Mockery::mock(FirebaseMessaging::class);
        $messaging->shouldReceive('sendMulticast')->andReturn($this->makeReport(['good'], ['bad']));

        $ch = $this->makeChannelWithMessaging($messaging);
        $this->send($ch, ['good', 'bad']);

        $this->assertTrue(\App\Modules\Core\Models\FcmToken::where('fcm_token', 'good')->exists());
        $this->assertFalse(\App\Modules\Core\Models\FcmToken::where('fcm_token', 'bad')->exists());
    }
}
