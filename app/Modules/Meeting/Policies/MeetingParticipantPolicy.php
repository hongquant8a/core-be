<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingParticipant;

/**
 * Policy item-level cho participant.
 *
 * Check meeting-level list → MeetingPolicy::viewParticipant qua `can:viewParticipant,meeting`.
 */
class MeetingParticipantPolicy
{
    /**
     * Confirm / decline lời mời — chỉ chính đại biểu được respond cho participant của mình.
     */
    public function respond(User $user, MeetingParticipant $participant): bool
    {
        $attendee = $participant->attendee;

        return $attendee && (int) $attendee->user_id === (int) $user->id;
    }

    /**
     * Xem chi tiết 1 participant — chair/op HOẶC chính participant đó.
     */
    public function view(User $user, MeetingParticipant $participant): bool
    {
        $meeting = $participant->meeting;
        if (! $meeting) {
            return false;
        }
        if ($meeting->isChairperson($user) || $meeting->isOperator($user)) {
            return true;
        }
        $attendee = $participant->attendee;

        return $attendee && (int) $attendee->user_id === (int) $user->id;
    }
}
