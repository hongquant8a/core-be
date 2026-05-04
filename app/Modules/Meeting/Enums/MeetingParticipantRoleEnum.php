<?php

namespace App\Modules\Meeting\Enums;

enum MeetingParticipantRoleEnum: string
{
    case Delegate = 'delegate';
    case Chairperson = 'chairperson';
    case Operator = 'operator';
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
