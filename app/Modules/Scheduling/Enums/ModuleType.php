<?php

namespace App\Modules\Scheduling\Enums;

enum ModuleType: string
{
    case EXECUTIVE = 'EXECUTIVE';
    case OFFICE = 'OFFICE';

    public static function values(): array { return array_column(self::cases(), 'value'); }
    public static function rule(): string  { return 'in:' . implode(',', self::values()); }
}
