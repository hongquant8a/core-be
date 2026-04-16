<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\NotificationService;
use Tests\TestCase;

class NotificationServiceProviderTest extends TestCase
{
    public function test_notification_service_is_singleton(): void
    {
        $a = app(NotificationService::class);
        $b = app(NotificationService::class);
        $this->assertSame($a, $b);
    }

    public function test_unknown_channel_returns_failure(): void
    {
        $svc = app(NotificationService::class);
        $results = $svc->send(new NotificationPayload(
            channels: ['nonexistent'],
            recipient: new Recipient(phone: '0905112233'),
            content: 'hi',
        ));
        $this->assertFalse($results[0]->success);
        $this->assertSame('Unknown channel: nonexistent', $results[0]->error);
    }

    public function test_all_channels_registered(): void
    {
        $svc = app(NotificationService::class);
        $payload = new NotificationPayload(
            channels: ['sms', 'mail', 'zalo', 'fcm'],
            recipient: new Recipient(phone: '0905112233', email: 'a@b.c', zaloId: 'z', fcmToken: 'tok'),
            content: 'hi',
        );
        $results = $svc->send($payload);
        $this->assertCount(4, $results);
        foreach ($results as $r) {
            $this->assertStringNotContainsString('Unknown channel', $r->error ?? '');
        }
    }
}
