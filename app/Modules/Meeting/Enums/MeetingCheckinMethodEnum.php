<?php

namespace App\Modules\Meeting\Enums;

enum MeetingCheckinMethodEnum: string
{
    case Qr = 'qr';
    case Button = 'button';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
