<?php

namespace App\Modules\Scheduling\Enums;

enum ReminderSourceEnum: string
{
    case Preset = 'preset';
    case Custom = 'custom';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
