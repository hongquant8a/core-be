<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ZaloChannelTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function makeSettings(
        bool $enabled = true,
        ?string $appId = '4105620146849935658',
        ?string $appSecret = 'sk-secret',
        ?string $accessToken = 'access-1',
        ?string $refreshToken = 'refresh-1',
    ): SettingService {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('zalo_enabled')->andReturn(['value' => $enabled ? '1' : '0']);
        $m->shouldReceive('getByKey')->with('zalo_app_id')->andReturn($appId === null ? null : ['value' => $appId]);
        $m->shouldReceive('getByKey')->with('zalo_app_secret')->andReturn($appSecret === null ? null : ['value' => $appSecret]);
        $m->shouldReceive('getByKey')->with('zalo_access_token')->andReturn($accessToken === null ? null : ['value' => $accessToken]);
        $m->shouldReceive('getByKey')->with('zalo_refresh_token')->andReturn($refreshToken === null ? null : ['value' => $refreshToken]);
        // Default: update không bị gọi — test nào cần thì override.
        $m->shouldReceive('update')->byDefault();

        return $m;
    }

    private function send(ZaloChannel $ch, ?string $zaloId, string $content = 'Hello'): SendResult
    {
        $recipient = new Recipient(zaloId: $zaloId);
        $payload = new NotificationPayload(['zalo'], $recipient, $content);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_zalo(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $this->assertSame('zalo', $ch->key());
    }

    public function test_fail_when_disabled(): void
    {
        $ch = new ZaloChannel($this->makeSettings(enabled: false));
        $r = $this->send($ch, 'user123');
        $this->assertFalse($r->success);
        $this->assertSame('Zalo is disabled', $r->error);
    }

    public function test_fail_when_access_token_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(accessToken: null));
        $r = $this->send($ch, 'user123');
        $this->assertFalse($r->success);
        $this->assertStringContainsString('access_token', $r->error);
    }

    public function test_sends_with_only_access_token_when_send_succeeds(): void
    {
        Http::fake([
            'openapi.zalo.me/*' => Http::response(['error' => 0, 'message' => 'OK', 'data' => ['message_id' => 'ok-1']]),
        ]);

        // Chỉ access_token, các credential khác null — vẫn gửi được.
        $ch = new ZaloChannel($this->makeSettings(appId: null, appSecret: null, refreshToken: null));
        $r = $this->send($ch, 'user123', 'hi');

        $this->assertTrue($r->success);
        $this->assertSame('ok-1', $r->messageId);
    }

    public function test_fails_with_clear_message_when_token_expired_and_no_refresh_creds(): void
    {
        Http::fake([
            'openapi.zalo.me/*' => Http::response(['error' => -124, 'message' => 'Access token is invalid']),
        ]);

        $ch = new ZaloChannel($this->makeSettings(appId: null, appSecret: null, refreshToken: null));
        $r = $this->send($ch, 'user123');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('auto-refresh', $r->error);
    }

    public function test_fail_when_zalo_user_id_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, null);
        $this->assertFalse($r->success);
        $this->assertSame('Missing Zalo user_id', $r->error);
    }

    public function test_fail_when_content_empty(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, 'user123', '   ');
        $this->assertFalse($r->success);
        $this->assertSame('Missing message text', $r->error);
    }

    public function test_sends_correct_payload_with_access_token_header(): void
    {
        Http::fake([
            'openapi.zalo.me/*' => Http::response(['error' => 0, 'message' => 'OK', 'data' => ['message_id' => 'msg-1']]),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, 'user-7186086631826132217', 'Xin chào Tuấn');

        $this->assertTrue($r->success);
        $this->assertSame('msg-1', $r->messageId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openapi.zalo.me/v2.0/oa/message'
                && $request->hasHeader('access_token', 'access-1')
                && $request['recipient']['user_id'] === 'user-7186086631826132217'
                && $request['message']['text'] === 'Xin chào Tuấn';
        });
    }

    public function test_returns_error_on_non_token_failure(): void
    {
        Http::fake([
            'openapi.zalo.me/*' => Http::response(['error' => -32, 'message' => 'User has not interacted within 24h']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, 'user123');

        $this->assertFalse($r->success);
        $this->assertSame('[-32] User has not interacted within 24h', $r->error);
    }

    public function test_refreshes_token_and_retries_on_token_error(): void
    {
        $callCount = 0;
        Http::fake([
            'openapi.zalo.me/*' => function () use (&$callCount) {
                $callCount++;

                return $callCount === 1
                    ? Http::response(['error' => -124, 'message' => 'Access token is invalid'])
                    : Http::response(['error' => 0, 'message' => 'OK', 'data' => ['message_id' => 'msg-after-refresh']]);
            },
            'oauth.zaloapp.com/*' => Http::response(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => '90000']),
        ]);

        $settings = $this->makeSettings();
        $settings->shouldReceive('update')
            ->once()
            ->with(['zalo_access_token' => 'access-2', 'zalo_refresh_token' => 'refresh-2']);

        $ch = new ZaloChannel($settings);
        $r = $this->send($ch, 'user123', 'hi');

        $this->assertTrue($r->success);
        $this->assertSame('msg-after-refresh', $r->messageId);

        // Send #1 dùng access-1, refresh, send #2 dùng access-2.
        Http::assertSentCount(3);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'oauth.zaloapp.com')
            && $req['app_id'] === '4105620146849935658'
            && $req['refresh_token'] === 'refresh-1'
            && $req['grant_type'] === 'refresh_token'
            && $req->hasHeader('secret_key', 'sk-secret')
        );
    }

    public function test_fails_when_refresh_returns_invalid_response(): void
    {
        Http::fake([
            'openapi.zalo.me/*' => Http::response(['error' => -124, 'message' => 'Invalid access token']),
            'oauth.zaloapp.com/*' => Http::response(['error' => -1, 'error_description' => 'Invalid refresh token']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, 'user123');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Refresh token failed', $r->error);
    }

    public function test_returns_error_when_http_throws(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection timeout');
        });

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, 'user123');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Connection timeout', $r->error);
    }
}
