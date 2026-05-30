<?php

namespace App\Modules\Scheduling\Enums;

enum ViewFilterEnum: string
{
    case Personal = 'personal';
    case All = 'all';
    case Managed = 'managed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
