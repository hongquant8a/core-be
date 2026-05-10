<?php

namespace App\Modules\Meeting\Concerns;

use App\Modules\Meeting\Models\Meeting;

/**
 * Role detection per cuộc họp — dùng cho enforce visibility ballot_mode + flag hiển thị
 * trong endpoint biểu quyết, đăng ký phát biểu, attendance.
 *
 *  - "privileged" = chair / operator của meeting (FK match) HOẶC role org-wide
 *    (Super Admin, Admin, Quản trị). Privileged thấy chi tiết per-person.
 *  - "delegate" = participant của meeting (đại biểu thường). Delegate chỉ thấy aggregate
 *    nếu flag hiển thị bật.
 */
trait HasMeetingRole
{
    protected function isPrivilegedForMeeting(?Meeting $meeting): bool
    {
        if (! $meeting) {
            return false;
        }

        $userId = auth()->id();
        if (! $userId) {
            return false;
        }

        $meeting->loadMissing(['chairperson', 'operator']);
        if ((int) ($meeting->chairperson?->user_id ?? 0) === (int) $userId) {
            return true;
        }
        if ((int) ($meeting->operator?->user_id ?? 0) === (int) $userId) {
            return true;
        }

        $user = auth()->user();

        return $user && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị']);
    }
}
