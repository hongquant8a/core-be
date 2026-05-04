<?php

namespace App\Modules\Meeting\Enums;

enum MeetingStatusEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
    case InProgress = 'in_progress';
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
