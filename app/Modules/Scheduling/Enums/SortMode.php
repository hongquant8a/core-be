<?php

namespace App\Modules\Scheduling\Enums;

enum SortMode: string
{
    case TIME = 'time';
    case POSITION = 'position';
    case MANUAL = 'manual';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
