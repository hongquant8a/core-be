<?php

namespace App\Modules\Meeting\Enums;

/**
 * 3 trạng thái theo spec phòng họp không giấy:
 *  - Pending  → "Chờ xác nhận" (mới gửi mời / chưa thao tác)
 *  - Present  → "Có mặt"
 *  - Absent   → "Vắng mặt" (đại biểu báo vắng, kèm lý do)
 */
enum MeetingAttendanceStatusEnum: string
{
    case Pending = 'pending';
    case Present = 'present';
    case Absent = 'absent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
