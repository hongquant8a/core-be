<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingVoteResponse;

/**
 * Policy item-level cho phản hồi biểu quyết.
 *
 * Check cast vote → dùng MeetingVoteTopicPolicy::cast qua `can:cast,meetingVoteTopic`.
 * Check meeting-level list/export → MeetingPolicy::viewParticipant / operate qua `can:...,meeting`.
 */
class MeetingVoteResponsePolicy
{
    /**
     * Xem chi tiết 1 response — owner HOẶC chair/op của meeting cha.
     */
    public function view(User $user, MeetingVoteResponse $response): bool
    {
        if ((int) $response->user_id === (int) $user->id) {
            return true;
        }
        $meeting = $response->topic?->meeting;

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }

    /**
     * Xóa response — chỉ chair OR operator.
     */
    public function delete(User $user, MeetingVoteResponse $response): bool
    {
        $meeting = $response->topic?->meeting;

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }
}
