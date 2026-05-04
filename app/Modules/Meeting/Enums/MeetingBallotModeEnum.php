<?php

namespace App\Modules\Meeting\Enums;

enum MeetingBallotModeEnum: string
{
    case Anonymous = 'anonymous';
    case PublicNamed = 'public_named';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
