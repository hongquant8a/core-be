<?php

namespace Tests\Unit\Services\Notification\Enums;

use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationMomentEnum;
use PHPUnit\Framework\TestCase;

class NotificationEventEnumTest extends TestCase
{
    public function test_event_values(): void
    {
        $this->assertSame('document_issued', NotificationEventEnum::DocumentIssued->value);
        $this->assertSame('task_completed', NotificationEventEnum::TaskCompleted->value);
        $this->assertSame('task_confirmed', NotificationEventEnum::TaskConfirmed->value);
        $this->assertSame('reminder_before', NotificationEventEnum::ReminderBefore->value);
        $this->assertSame('reminder_on', NotificationEventEnum::ReminderOn->value);
        $this->assertSame('reminder_after', NotificationEventEnum::ReminderAfter->value);
    }

    public function test_values_returns_all(): void
    {
        $values = NotificationEventEnum::values();
        $this->assertContains('document_issued', $values);
        $this->assertCount(6, $values);
    }

    public function test_moment_values(): void
    {
        $this->assertSame('before', NotificationMomentEnum::Before->value);
        $this->assertSame('on', NotificationMomentEnum::On->value);
        $this->assertSame('after', NotificationMomentEnum::After->value);
    }
}
