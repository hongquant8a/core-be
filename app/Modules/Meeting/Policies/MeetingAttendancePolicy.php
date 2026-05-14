<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendance;

/**
 * Policy item-level cho điểm danh.
 *
 * Check meeting-level: self checkin → MeetingPolicy::participate;
 * manualCheckin → MeetingPolicy::manageAttendance;
 * list/stats/export → MeetingPolicy::viewParticipant hoặc operate.
 */
class MeetingAttendancePolicy
{
    /**
     * Xem chi tiết 1 attendance — owner HOẶC chair/op.
     */
    public function view(User $user, MeetingAttendance $attendance): bool
    {
        $meeting = $this->resolveMeeting($attendance);
        if (! $meeting) {
            return false;
        }
        if ($meeting->isChairperson($user) || $meeting->isOperator($user)) {
            return true;
        }
        $attendee = $attendance->participant?->attendee;

        return $attendee && (int) $attendee->user_id === (int) $user->id;
    }

    /**
     * Duyệt điểm danh — chair OR operator.
     */
    public function approve(User $user, MeetingAttendance $attendance): bool
    {
        $meeting = $this->resolveMeeting($attendance);

        return $meeting && ($meeting->isChairperson($user) || $meeting->isOperator($user));
    }

    /**
     * Từ chối điểm danh — chair OR operator.
     */
    public function reject(User $user, MeetingAttendance $attendance): bool
    {
        return $this->approve($user, $attendance);
    }

    /**
     * Lấy Meeting từ attendance — ưu tiên FK trực tiếp, fallback qua participant.
     */
    private function resolveMeeting(MeetingAttendance $attendance): ?Meeting
    {
        if ($attendance->meeting_id) {
            return Meeting::find($attendance->meeting_id);
        }

        return $attendance->participant?->meeting;
    }
}
