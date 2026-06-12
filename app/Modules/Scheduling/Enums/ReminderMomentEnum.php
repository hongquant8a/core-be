<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderMomentEnum: string
{
    case Immediate = 'IMMEDIATE';
    case Before    = 'BEFORE';
    case On        = 'ON';
    case After     = 'AFTER';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
