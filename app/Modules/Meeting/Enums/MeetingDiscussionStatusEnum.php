<?php

namespace App\Modules\Meeting\Enums;

/**
 * Trạng thái đăng ký thảo luận / chất vấn — đơn giản nhị phân:
 *  - Registered: đã đăng ký, chưa phát biểu / chưa chất vấn.
 *  - Completed:  đã phát biểu / đã chất vấn (operator đánh dấu xong).
 *
 * Đại biểu rút đăng ký trước khi xong = xóa row (DELETE endpoint), không có "cancelled".
 */
enum MeetingDiscussionStatusEnum: string
{
    case Registered = 'registered';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
