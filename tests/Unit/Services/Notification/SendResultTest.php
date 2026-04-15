<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\SendResult;
use PHPUnit\Framework\TestCase;

class SendResultTest extends TestCase
{
    public function test_success_result(): void
    {
        $r = new SendResult(channel: 'sms', success: true, messageId: '42');
        $this->assertSame('sms', $r->channel);
        $this->assertTrue($r->success);
        $this->assertSame('42', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_result(): void
    {
        $r = new SendResult(channel: 'sms', success: false, error: 'boom');
        $this->assertFalse($r->success);
        $this->assertNull($r->messageId);
        $this->assertSame('boom', $r->error);
    }
}
