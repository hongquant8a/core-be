<?php

namespace App\Modules\Meeting\Enums;

enum MeetingDiscussionTypeEnum: string
{
    case Discussion = 'discussion';
    case Question = 'question';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
