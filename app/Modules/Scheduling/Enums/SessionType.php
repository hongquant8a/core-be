<?php

namespace App\Modules\Scheduling\Enums;

enum SessionType: string
{
    case MORNING = 'S';
    case AFTERNOON = 'C';
    case EVENING = 'T';

    public static function fromTime(string $startTime): self
    {
        $hour = (int) substr($startTime, 0, 2);
        return match(true) {
            $hour < 12 => self::MORNING,
            $hour < 18 => self::AFTERNOON,
            default => self::EVENING,
        };
    }
}
