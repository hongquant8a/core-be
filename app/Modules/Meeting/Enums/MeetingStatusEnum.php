<?php

namespace App\Modules\Meeting\Enums;

/**
 * Enum trạng thái meeting — chỉ giữ 3 trạng thái thực sự cần manual.
 * `in_progress` / `completed` derived ở FE từ start_time/end_time vs now.
 */
enum MeetingStatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
