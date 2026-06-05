<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleStatus: int
{
    case DRAFT = 0;
    case PUBLISHED = 1;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
