<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleSessionEnum: string
{
    case Morning   = 'MORNING';
    case Afternoon = 'AFTERNOON';
    case Evening   = 'EVENING';
    case AllDay    = 'ALL_DAY';

    public function label(): string
    {
        return match($this) {
            self::Morning   => 'Buổi sáng',
            self::Afternoon => 'Buổi chiều',
            self::Evening   => 'Buổi tối',
            self::AllDay    => 'Cả ngày',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
