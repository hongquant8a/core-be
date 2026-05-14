<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;

/**
 * Policy item-level cho đăng ký thảo luận/chất vấn.
 *
 * Check meeting-level (list/create/reorder/export) → dùng MeetingPolicy qua `can:...,meeting`.
 * Check item-level (view/update/delete/complete) → dùng class này qua `can:...,meetingDiscussionRegistration`.
 *
 * Quy ước owner: $reg.participant.attendee.user_id === current user.id
 */
class MeetingDiscussionRegistrationPolicy
{
    /**
     * Xem chi tiết 1 reg — owner hoặc chair/op của meeting cha.
     */
    public function view(User $user, MeetingDiscussionRegistration $reg): bool
    {
        if ($this->isOwner($user, $reg)) {
            return true;
        }
        $meeting = $reg->meeting;

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }

    /**
     * Cập nhật đăng ký — owner hoặc chair/op.
     */
    public function update(User $user, MeetingDiscussionRegistration $reg): bool
    {
        return $this->view($user, $reg);
    }

    /**
     * Xóa đăng ký — owner hoặc chair/op.
     */
    public function delete(User $user, MeetingDiscussionRegistration $reg): bool
    {
        return $this->view($user, $reg);
    }

    /**
     * Đánh dấu hoàn thành lượt phát biểu — chỉ chair OR operator.
     */
    public function complete(User $user, MeetingDiscussionRegistration $reg): bool
    {
        $meeting = $reg->meeting;

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }

    /**
     * Owner = user trùng với meeting_participant_id → attendee.user_id.
     */
    private function isOwner(User $user, MeetingDiscussionRegistration $reg): bool
    {
        $participant = $reg->participant;
        if (! $participant) {
            return false;
        }
        $attendee = $participant->attendee;

        return $attendee && (int) $attendee->user_id === (int) $user->id;
    }
}
