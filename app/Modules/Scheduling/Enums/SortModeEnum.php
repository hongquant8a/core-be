<?php

namespace App\Modules\Scheduling\Enums;

enum SortModeEnum: string
{
    case Time = 'time';
    case Position = 'position';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
