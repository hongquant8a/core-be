<?php

namespace App\Modules\Meeting\Enums;

enum MeetingVoteTopicStatusEnum: string
{
    case Draft = 'draft';
    case Opened = 'opened';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
