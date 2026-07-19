<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderSource: string
{
    case PRESET = 'PRESET';
    case CUSTOM = 'CUSTOM';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
