<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderStatusEnum: string
{
    case Pending   = 'PENDING';
    case Sent      = 'SENT';
    case Failed    = 'FAILED';
    case Cancelled = 'CANCELLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
