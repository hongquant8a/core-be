<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingVoteTopic;

/**
 * Policy cho vote topic phase control — open/close chỉ chủ trì meeting cha.
 *
 * Super Admin bypass qua Gate::before trong CoreServiceProvider.
 */
class MeetingVoteTopicPolicy
{
    /**
     * Mở biểu quyết — chủ trì hoặc thư ký meeting.
     */
    public function open(User $user, MeetingVoteTopic $topic): bool
    {
        return $topic->meeting !== null
            && ($topic->meeting->isChairperson($user) || $topic->meeting->isOperator($user));
    }

    /**
     * Đóng biểu quyết — chủ trì hoặc thư ký meeting.
     */
    public function close(User $user, MeetingVoteTopic $topic): bool
    {
        return $topic->meeting !== null
            && ($topic->meeting->isChairperson($user) || $topic->meeting->isOperator($user));
    }
}
