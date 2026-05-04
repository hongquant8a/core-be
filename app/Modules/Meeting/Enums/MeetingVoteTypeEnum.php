<?php

namespace App\Modules\Meeting\Enums;

enum MeetingVoteTypeEnum: string
{
    case AgreeDisagreeAbstain = 'agree_disagree_abstain';
    case ApproveRejectAbstain = 'approve_reject_abstain';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
