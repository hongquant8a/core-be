<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;

/**
 * Policy cho in-meeting control actions — check user là chair/operator/participant của
 * CHÍNH meeting đó (resource-based), không chỉ role-based qua Spatie.
 *
 * Super Admin bypass qua Gate::before trong CoreServiceProvider.
 *
 * Spatie permission vẫn dùng cho catalog/CRUD (index/show/store/update/destroy/dashboard).
 */
class MeetingPolicy
{
    /**
     * Kết thúc cuộc họp sớm — chủ trì hoặc thư ký (thư ký là người điều hành thực tế).
     */
    public function endEarly(User $user, Meeting $meeting): bool
    {
        return $meeting->isChairperson($user) || $meeting->isOperator($user);
    }

    /**
     * Lock/unlock điểm danh + manual checkin — chủ trì hoặc thư ký.
     */
    public function manageAttendance(User $user, Meeting $meeting): bool
    {
        return $meeting->isChairperson($user) || $meeting->isOperator($user);
    }

    /**
     * Highlight chương trình họp / discussion — chủ trì hoặc thư ký điều hành.
     */
    public function highlight(User $user, Meeting $meeting): bool
    {
        return $meeting->isChairperson($user) || $meeting->isOperator($user);
    }

    /**
     * Tham gia meeting (đại biểu/chủ trì/thư ký) — cho self-action như checkin/respond invitation.
     */
    public function participate(User $user, Meeting $meeting): bool
    {
        return $meeting->userMeetingRole($user) !== null;
    }

    /**
     * Operate — chủ trì hoặc thư ký được xuất báo cáo / export danh sách của meeting.
     * Đại biểu thường KHÔNG được truy cập export (chỉ xem dữ liệu của mình qua read API).
     */
    public function operate(User $user, Meeting $meeting): bool
    {
        return $meeting->isChairperson($user) || $meeting->isOperator($user);
    }
}
