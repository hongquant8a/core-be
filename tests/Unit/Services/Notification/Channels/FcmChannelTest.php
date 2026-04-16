<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\FcmChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FcmChannelTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function makeSettings(
        ?string $url = 'https://fcm.test/send',
        ?string $token = 'server-key-123',
        bool $enabled = true,
    ): SettingService {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('api_firebase_url')->andReturn($url === null ? null : ['value' => $url]);
        $m->shouldReceive('getByKey')->with('api_firebase_token')->andReturn($token === null ? null : ['value' => $token]);
        $m->shouldReceive('getByKey')->with('api_firebase_enabled')->andReturn(['value' => $enabled ? '1' : '0']);

        return $m;
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

    public function test_returns_failure_when_url_missing(): void
    {
        $ch = new FcmChannel($this->makeSettings(url: null));
        $r = $this->send($ch, 'token-1');
        $this->assertFalse($r->success);
        $this->assertSame('FCM not configured', $r->error);
    }

    public function test_returns_failure_when_server_key_missing(): void
    {
        $ch = new FcmChannel($this->makeSettings(token: null));
        $r = $this->send($ch, 'token-1');
        $this->assertFalse($r->success);
        $this->assertSame('FCM not configured', $r->error);
    }

    public function test_returns_failure_when_fcm_token_missing(): void
    {
        $ch = new FcmChannel($this->makeSettings());
        $r = $this->send($ch, null);
        $this->assertFalse($r->success);
        $this->assertSame('Missing FCM device token', $r->error);
    }

    public function test_sends_correct_payload_to_fcm(): void
    {
        Http::fake([
            'fcm.test/*' => Http::response(['success' => 1, 'results' => [['message_id' => 'fcm-msg-1']]]),
        ]);

        $ch = new FcmChannel($this->makeSettings());
        $this->send($ch, 'device-token-abc', 'Hello', 'Title', ['task_id' => 5]);

        Http::assertSent(function ($request) {
            return $request['to'] === 'device-token-abc'
                && $request['notification']['title'] === 'Title'
                && $request['notification']['body'] === 'Hello'
                && $request['data']['task_id'] === 5
                && str_contains($request->header('Authorization')[0], 'key=server-key-123');
        });
    }

    public function test_uses_default_title_when_no_subject(): void
    {
        Http::fake([
            'fcm.test/*' => Http::response(['success' => 1, 'results' => [['message_id' => 'fcm-msg-2']]]),
        ]);

        $ch = new FcmChannel($this->makeSettings());
        $this->send($ch, 'device-token-abc', 'Hello');

        Http::assertSent(function ($request) {
            return $request['notification']['title'] === 'Thông báo';
        });
    }

    public function test_omits_data_when_context_empty(): void
    {
        Http::fake([
            'fcm.test/*' => Http::response(['success' => 1, 'results' => [['message_id' => 'fcm-msg-3']]]),
        ]);

        $ch = new FcmChannel($this->makeSettings());
        $this->send($ch, 'device-token-abc', 'Hello');

        Http::assertSent(function ($request) {
            return ! isset($request['data']);
        });
    }

    public function test_success_when_fcm_returns_success_1(): void
    {
        Http::fake([
            'fcm.test/*' => Http::response(['success' => 1, 'results' => [['message_id' => 'msg-abc']]]),
        ]);

        $ch = new FcmChannel($this->makeSettings());
        $r = $this->send($ch, 'device-token');

        $this->assertTrue($r->success);
        $this->assertSame('msg-abc', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_when_fcm_returns_success_0(): void
    {
        Http::fake([
            'fcm.test/*' => Http::response(['success' => 0, 'results' => [['error' => 'InvalidRegistration']]]),
        ]);

        $ch = new FcmChannel($this->makeSettings());
        $r = $this->send($ch, 'bad-token');

        $this->assertFalse($r->success);
        $this->assertSame('InvalidRegistration', $r->error);
    }

    public function test_catches_http_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Network down');
        });

        $ch = new FcmChannel($this->makeSettings());
        $r = $this->send($ch, 'device-token');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Network down', $r->error);
    }
}
