<?php

namespace App\Services\Notification\Enums;

enum NotificationEventEnum: string
{
    case DocumentIssued = 'document_issued';
    case TaskAssigned = 'task_assigned';
    case TaskCompleted = 'task_completed';
    case TaskConfirmed = 'task_confirmed';
    case ReminderBefore = 'reminder_before';
    case ReminderOn = 'reminder_on';
    case ReminderAfter = 'reminder_after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Module sở hữu event này — dùng để filter config theo module cho FE.
     */
    public function module(): NotificationModuleEnum
    {
        return match ($this) {
            self::DocumentIssued,
            self::TaskAssigned,
            self::TaskCompleted,
            self::TaskConfirmed,
            self::ReminderBefore,
            self::ReminderOn,
            self::ReminderAfter => NotificationModuleEnum::TaskAssignment,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DocumentIssued => 'Văn bản được ban hành',
            self::TaskAssigned => 'Được giao việc mới',
            self::TaskCompleted => 'Công việc báo cáo hoàn thành',
            self::TaskConfirmed => 'Công việc được xác nhận',
            self::ReminderBefore => 'Nhắc trước hạn',
            self::ReminderOn => 'Nhắc đến hạn',
            self::ReminderAfter => 'Nhắc quá hạn',
        };
    }
}
