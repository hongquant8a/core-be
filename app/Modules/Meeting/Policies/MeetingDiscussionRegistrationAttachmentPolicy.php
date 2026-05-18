<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingDiscussionRegistrationAttachment;

/**
 * Policy item-level cho attachment của thảo luận / chất vấn.
 *
 * Owner check thông qua registration cha: owner = participant.attendee.user_id
 * (đại biểu đăng ký). Chair/operator cũng được sửa attachment (đồng bộ với
 * MeetingDiscussionRegistrationPolicy::update — họ có thể chỉnh ghi chú).
 */
class MeetingDiscussionRegistrationAttachmentPolicy
{
    public function update(User $user, MeetingDiscussionRegistrationAttachment $att): bool
    {
        return $this->canModifyParent($user, $att);
    }

    public function delete(User $user, MeetingDiscussionRegistrationAttachment $att): bool
    {
        return $this->canModifyParent($user, $att);
    }

    private function canModifyParent(User $user, MeetingDiscussionRegistrationAttachment $att): bool
    {
        $registration = $att->registration;
        if (! $registration) {
            return false;
        }
        $attendee = $registration->participant?->attendee;
        if ($attendee && (int) $attendee->user_id === (int) $user->id) {
            return true;
        }
        $meeting = $registration->meeting;

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }
}
