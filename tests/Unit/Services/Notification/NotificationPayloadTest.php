<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use PHPUnit\Framework\TestCase;

class NotificationPayloadTest extends TestCase
{
    public function test_constructs_with_required_and_optional_fields(): void
    {
        $recipient = new Recipient(phone: '0905112233');
        $p = new NotificationPayload(
            channels: ['sms', 'mail'],
            recipient: $recipient,
            content: 'hello',
            subject: 'subj',
            context: ['task_id' => 7],
        );

        $this->assertSame(['sms', 'mail'], $p->channels);
        $this->assertSame($recipient, $p->recipient);
        $this->assertSame('hello', $p->content);
        $this->assertSame('subj', $p->subject);
        $this->assertSame(['task_id' => 7], $p->context);
    }

    public function test_subject_and_context_default(): void
    {
        $p = new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'x');
        $this->assertNull($p->subject);
        $this->assertSame([], $p->context);
    }
}
