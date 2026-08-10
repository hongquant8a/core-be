<?php

namespace App\Modules\Meeting\Enums;

enum MeetingHomeDisplayTypeEnum: string
{
    case StatusType = 'status_type';
    case MeetingType = 'meeting_type';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::StatusType => 'Giao diện theo trạng thái',
            self::MeetingType => 'Giao diện theo loại cuộc họp',
        };
    }
}
