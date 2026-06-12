<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderMomentEnum: string
{
    case Before = 'before';
    case On     = 'on';
    case After  = 'after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
