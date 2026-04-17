<?php

namespace App\Services\Notification\Enums;

enum NotificationEventEnum: string
{
    case DocumentIssued = 'document_issued';
    case TaskCompleted = 'task_completed';
    case TaskConfirmed = 'task_confirmed';
    case ReminderBefore = 'reminder_before';
    case ReminderOn = 'reminder_on';
    case ReminderAfter = 'reminder_after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
