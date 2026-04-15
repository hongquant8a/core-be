<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\SmsClient;
use Mockery;
use PHPUnit\Framework\TestCase;
use SoapFault;

class SmsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSettings(?string $server, ?string $user, ?string $pass): SettingService
    {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('sms_server')->andReturn($server === null ? null : ['value' => $server]);
        $m->shouldReceive('getByKey')->with('sms_username')->andReturn($user === null ? null : ['value' => $user]);
        $m->shouldReceive('getByKey')->with('sms_password')->andReturn($pass === null ? null : ['value' => $pass]);

        return $m;
    }

    private function fakeClient(callable $sendImpl): SmsClient
    {
        return new class($sendImpl) extends SmsClient
        {
            public function __construct(private $impl) {}

            public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array
            {
                return ($this->impl)($url, $user, $pass, $phone, $content);
            }
        };
    }

    private function send(SmsChannel $ch, ?string $phone, string $content): \App\Services\Notification\DTOs\SendResult
    {
        $recipient = new Recipient(phone: $phone);
        $payload   = new NotificationPayload(['sms'], $recipient, $content);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_sms(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $this->assertSame('sms', $ch->key());
    }

    public function test_returns_failure_when_server_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => 'should not call']),
            $this->makeSettings(null, 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_username_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', null, 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_password_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', null),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_phone_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, null, 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('Missing phone', $r->error);
    }

    public function test_returns_failure_when_phone_invalid(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '12345', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('Invalid phone format', $r->error);
    }

    public function test_normalizes_phone_with_leading_zero_to_84_prefix(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: hi');
        $this->assertSame('84905112233', $captured[3]);
    }

    public function test_passes_through_phone_already_starting_with_84(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '84905112233', 'Thong bao: hi');
        $this->assertSame('84905112233', $captured[3]);
    }

    public function test_strips_vietnamese_diacritics_from_content(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: Xin chào bạn');
        $this->assertStringNotContainsString('à', $captured[4]);
        $this->assertStringContainsString('Xin chao ban', $captured[4]);
    }

    public function test_auto_prefixes_thong_bao_when_neither_marker_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Hello');
        $this->assertSame('Thong bao: Hello', $captured[4]);
    }

    public function test_does_not_prefix_when_thong_bao_already_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: existing');
        $this->assertSame('Thong bao: existing', $captured[4]);
    }

    public function test_does_not_prefix_when_tran_trong_suffix_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Hello. Tran trong !');
        $this->assertSame('Hello. Tran trong !', $captured[4]);
    }

    public function test_success_when_result_is_zero_or_positive(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => 'ok']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertTrue($r->success);
        $this->assertSame('0', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_when_result_is_negative(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => -3, 'message' => 'invalid phone']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('invalid phone', $r->error);
    }

    public function test_catches_soap_fault_and_returns_failure(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(function () {
                throw new SoapFault('Server', 'connection refused');
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertStringContainsString('connection refused', $r->error);
    }
}
