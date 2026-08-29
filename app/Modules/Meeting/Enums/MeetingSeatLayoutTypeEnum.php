<?php

namespace App\Modules\Meeting\Enums;

enum MeetingSeatLayoutTypeEnum: string
{
    case Theater = 'theater';
    case Presidium = 'presidium';
    case Curved = 'curved';
    case Ushape = 'ushape';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
