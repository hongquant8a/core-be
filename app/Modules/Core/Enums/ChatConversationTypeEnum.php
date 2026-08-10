<?php

namespace App\Modules\Core\Enums;

enum ChatConversationTypeEnum: string
{
    case Direct = 'direct';
    case MeetingGroup = 'meeting_group';

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
            self::Direct => 'Nhắn tin riêng',
            self::MeetingGroup => 'Trao đổi nội bộ cuộc họp',
        };
    }
}
