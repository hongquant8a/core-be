<?php

namespace App\Modules\Meeting\Enums;

enum MeetingAttendanceStatusEnum: string
{
    case Pending = 'pending';
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
