<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\NotificationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NotificationServiceTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function fakeChannel(string $key, callable $impl): NotificationChannel
    {
        return new class($key, $impl) implements NotificationChannel
        {
            public function __construct(private string $k, private $impl) {}

            public function send(Recipient $r, NotificationPayload $p): SendResult
            {
                return ($this->impl)($r, $p);
            }

            public function key(): string
            {
                return $this->k;
            }
        };
    }

    private function payload(array $channels, string $content = 'hi'): NotificationPayload
    {
        return new NotificationPayload($channels, new Recipient(phone: '0905112233'), $content);
    }

    public function test_dispatches_to_correct_channel_by_key(): void
    {
        $called = null;
        $svc = new NotificationService(
            channels: [
                'sms' => $this->fakeChannel('sms', function () use (&$called) {
                    $called = 'sms';

                    return new SendResult('sms', true, '1');
                }),
            ],
        );

        $results = $svc->send($this->payload(['sms']));
        $this->assertSame('sms', $called);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->success);
    }

    public function test_returns_failure_for_unknown_channel_without_throwing(): void
    {
        $svc = new NotificationService(channels: []);
        $results = $svc->send($this->payload(['ghost']));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertSame('ghost', $results[0]->channel);
        $this->assertSame('Unknown channel: ghost', $results[0]->error);
    }

    public function test_dispatches_to_multiple_channels_in_order(): void
    {
        $svc = new NotificationService(
            channels: [
                'sms' => $this->fakeChannel('sms', fn () => new SendResult('sms', true, '1')),
                'mail' => $this->fakeChannel('mail', fn () => new SendResult('mail', false, error: 'nope')),
            ],
        );

        $results = $svc->send($this->payload(['sms', 'mail']));
        $this->assertCount(2, $results);
        $this->assertSame('sms', $results[0]->channel);
        $this->assertTrue($results[0]->success);
        $this->assertSame('mail', $results[1]->channel);
        $this->assertFalse($results[1]->success);
    }

    public function test_wraps_channel_exception_into_failure_result(): void
    {
        $svc = new NotificationService(
            channels: [
                'sms' => $this->fakeChannel('sms', function () {
                    throw new RuntimeException('boom');
                }),
            ],
        );

        $results = $svc->send($this->payload(['sms']));
        $this->assertFalse($results[0]->success);
        $this->assertSame('sms', $results[0]->channel);
        $this->assertStringContainsString('boom', $results[0]->error);
    }
}
