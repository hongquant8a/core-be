<?php

namespace App\Modules\Scheduling\Enums;

enum Nature: string
{
    case HOST = 'HOST';
    case ATTEND = 'ATTEND';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
