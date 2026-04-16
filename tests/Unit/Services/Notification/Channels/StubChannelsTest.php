<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use PHPUnit\Framework\TestCase;

class StubChannelsTest extends TestCase
{
    public function test_mail_channel_returns_not_implemented(): void
    {
        $ch = new MailChannel;
        $this->assertSame('mail', $ch->key());

        $result = $ch->send(
            new Recipient(email: 'a@b.c'),
            new NotificationPayload(['mail'], new Recipient(email: 'a@b.c'), 'hi'),
        );

        $this->assertSame('mail', $result->channel);
        $this->assertFalse($result->success);
        $this->assertSame('Not implemented yet', $result->error);
    }
}
