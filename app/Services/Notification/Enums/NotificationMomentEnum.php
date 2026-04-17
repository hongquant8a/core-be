<?php

namespace App\Services\Notification\Enums;

enum NotificationMomentEnum: string
{
    case Before = 'before';
    case On = 'on';
    case After = 'after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
