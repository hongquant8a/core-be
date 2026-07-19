<?php

namespace App\Modules\Scheduling\Enums;

enum ViewFilter: string
{
    case PERSONAL = 'personal';
    case ALL = 'all';
    case MANAGED = 'managed';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
