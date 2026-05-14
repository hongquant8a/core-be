<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingVoteTopic;

/**
 * Policy item-level cho vote topic.
 *
 * Check meeting-level list → MeetingPolicy::viewParticipant qua `can:viewParticipant,meeting`.
 */
class MeetingVoteTopicPolicy
{
    /**
     * Xem chi tiết 1 vote topic — user có role trong meeting cha.
     */
    public function view(User $user, MeetingVoteTopic $topic): bool
    {
        return $topic->meeting !== null
            && $topic->meeting->userMeetingRole($user) !== null;
    }

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

    /**
     * Cast vote — participant OR chair (NOT operator — operator điều hành kỹ thuật).
     */
    public function cast(User $user, MeetingVoteTopic $topic): bool
    {
        $meeting = $topic->meeting;
        if (! $meeting) {
            return false;
        }

        return $meeting->isChairperson($user) || $meeting->isParticipant($user);
    }
}
