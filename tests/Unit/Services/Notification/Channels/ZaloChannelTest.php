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
        ?string $server = 'https://api.test/sendZNS',
        ?string $user = 'u',
        ?string $pass = 'p',
        ?string $sender = 'oa123',
        ?string $templateId = 'tpl456',
        mixed $extraParams = null,
    ): SettingService {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('zalo_server')->andReturn($server === null ? null : ['value' => $server]);
        $m->shouldReceive('getByKey')->with('zalo_username')->andReturn($user === null ? null : ['value' => $user]);
        $m->shouldReceive('getByKey')->with('zalo_password')->andReturn($pass === null ? null : ['value' => $pass]);
        $m->shouldReceive('getByKey')->with('zalo_sender')->andReturn($sender === null ? null : ['value' => $sender]);
        $m->shouldReceive('getByKey')->with('zalo_template_id')->andReturn($templateId === null ? null : ['value' => $templateId]);
        $m->shouldReceive('getByKey')->with('zalo_extra_params')->andReturn($extraParams === null ? null : ['value' => $extraParams]);

        return $m;
    }

    private function send(ZaloChannel $ch, ?string $phone, string $content = 'hi', array $context = []): SendResult
    {
        $recipient = new Recipient(phone: $phone);
        $payload = new NotificationPayload(['zalo'], $recipient, $content, context: $context);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_zalo(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $this->assertSame('zalo', $ch->key());
    }

    public function test_returns_failure_when_server_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(server: null));
        $r = $this->send($ch, '0905112233');
        $this->assertFalse($r->success);
        $this->assertSame('Zalo not configured', $r->error);
    }

    public function test_returns_failure_when_username_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(user: null));
        $r = $this->send($ch, '0905112233');
        $this->assertFalse($r->success);
        $this->assertSame('Zalo not configured', $r->error);
    }

    public function test_returns_failure_when_password_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(pass: null));
        $r = $this->send($ch, '0905112233');
        $this->assertFalse($r->success);
        $this->assertSame('Zalo not configured', $r->error);
    }

    public function test_returns_failure_when_sender_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(sender: null));
        $r = $this->send($ch, '0905112233');
        $this->assertFalse($r->success);
        $this->assertSame('Missing Zalo OA sender ID', $r->error);
    }

    public function test_returns_failure_when_template_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings(templateId: null));
        $r = $this->send($ch, '0905112233');
        $this->assertFalse($r->success);
        $this->assertSame('Missing Zalo template ID', $r->error);
    }

    public function test_returns_failure_when_phone_missing(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, null);
        $this->assertFalse($r->success);
        $this->assertSame('Missing phone', $r->error);
    }

    public function test_returns_failure_when_phone_invalid(): void
    {
        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, '12345');
        $this->assertFalse($r->success);
        $this->assertSame('Invalid phone format', $r->error);
    }

    public function test_normalizes_phone_with_leading_zero(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 1, 'tracking_id' => 'track-1']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $this->send($ch, '0905112233');

        Http::assertSent(function ($request) {
            return $request['to'] === '84905112233';
        });
    }

    public function test_sends_correct_payload_to_api(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 1, 'tracking_id' => 'track-2']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $this->send($ch, '84905112233', 'hello', ['customer_name' => 'Tuan']);

        Http::assertSent(function ($request) {
            return $request['from'] === 'oa123'
                && $request['to'] === '84905112233'
                && $request['template_id'] === 'tpl456'
                && $request['template_data']['customer_name'] === 'Tuan'
                && isset($request['client_req_id'])
                && $request->hasHeader('Authorization');
        });
    }

    public function test_merges_extra_params_with_context(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 1, 'tracking_id' => 'track-3']),
        ]);

        $ch = new ZaloChannel($this->makeSettings(extraParams: '{"company":"Danatec"}'));
        $this->send($ch, '84905112233', 'hi', ['customer_name' => 'Tuan']);

        Http::assertSent(function ($request) {
            return $request['template_data']['company'] === 'Danatec'
                && $request['template_data']['customer_name'] === 'Tuan';
        });
    }

    public function test_context_overrides_extra_params(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 1, 'tracking_id' => 'track-4']),
        ]);

        $ch = new ZaloChannel($this->makeSettings(extraParams: '{"company":"Old"}'));
        $this->send($ch, '84905112233', 'hi', ['company' => 'New']);

        Http::assertSent(function ($request) {
            return $request['template_data']['company'] === 'New';
        });
    }

    public function test_success_when_status_is_1(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 1, 'tracking_id' => 'abc-123']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, '0905112233');

        $this->assertTrue($r->success);
        $this->assertSame('abc-123', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_when_status_is_0(): void
    {
        Http::fake([
            'api.test/*' => Http::response(['status' => 0, 'errorcode' => 541, 'description' => 'Invalid Zalo Sender']),
        ]);

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, '0905112233');

        $this->assertFalse($r->success);
        $this->assertSame('[541] Invalid Zalo Sender', $r->error);
    }

    public function test_catches_http_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection timeout');
        });

        $ch = new ZaloChannel($this->makeSettings());
        $r = $this->send($ch, '0905112233');

        $this->assertFalse($r->success);
        $this->assertStringContainsString('Connection timeout', $r->error);
    }
}
