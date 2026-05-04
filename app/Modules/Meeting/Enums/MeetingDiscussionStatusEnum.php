<?php

namespace App\Modules\Meeting\Enums;

enum MeetingDiscussionStatusEnum: string
{
    case Registered = 'registered';
    case Called = 'called';
    case Completed = 'completed';
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
