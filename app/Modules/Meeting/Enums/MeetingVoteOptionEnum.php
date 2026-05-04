<?php

namespace App\Modules\Meeting\Enums;

enum MeetingVoteOptionEnum: string
{
    case Agree = 'agree';
    case Disagree = 'disagree';
    case Approve = 'approve';
    case Reject = 'reject';
    case Abstain = 'abstain';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
