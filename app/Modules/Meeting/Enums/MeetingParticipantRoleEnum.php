<?php

namespace App\Modules\Meeting\Enums;

/**
 * Vai trò participant trong cuộc họp.
 * Chủ trì + Thư ký đã chuyển sang FK trên `meetings` (chairperson_meeting_attendee_id, operator_meeting_attendee_id).
 * Enum này chỉ phân loại "đại biểu thường" vs "khách mời".
 */
enum MeetingParticipantRoleEnum: string
{
    case Delegate = 'delegate';
    case Guest = 'guest';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
