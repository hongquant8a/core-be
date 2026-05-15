<?php

namespace App\Modules\Meeting\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;

/**
 * Policy cho các action gắn meeting cụ thể — check qua khóa ngoại của meeting
 * (chairperson_meeting_attendee_id, operator_meeting_attendee_id) và list participants.
 *
 * KHÔNG có Super Admin bypass — admin hệ thống phải có role thật trên meeting.
 *
 * Spatie permission vẫn dùng cho catalog/CRUD admin setup (index/show/store/update/destroy/dashboard).
 */
class MeetingPolicy
{
    /**
     * Xem meeting ở route public — guest cũng được nếu meeting public + published.
     * User auth không cần role: chỉ check is_public + status. Trang ngoài (citizen).
     */
    public function viewPublic(?User $user, Meeting $meeting): bool
    {
        return (bool) $meeting->is_public && $meeting->status === 'published';
    }

    /**
     * Xem nội dung participant-only (Tab 3 Discussions, Tab 4 Voting...).
     * User phải có role chair/operator/participant trong CHÍNH meeting đó.
     */
    public function viewParticipant(User $user, Meeting $meeting): bool
    {
        return $meeting->userMeetingRole($user) !== null;
    }

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

    /**
     * Bật QR điểm danh — chủ trì OR thư ký OR user được gán làm qr_manager.
     * KHÔNG fallback Spatie permission. Phân quyền dựa trên khóa ngoại meeting.
     */
    public function showQrCode(User $user, Meeting $meeting): bool
    {
        if ($meeting->isChairperson($user) || $meeting->isOperator($user)) {
            return true;
        }

        return (int) ($meeting->qr_manager_user_id ?? 0) === (int) $user->id;
    }

    /**
     * Xuất 4 báo cáo tổng hợp 1 cuộc họp (biên bản .docx, thảo luận/chất vấn .xlsx,
     * biểu quyết .xlsx tổng hợp, điểm danh .xlsx).
     *
     * Cho phép 2 đối tượng:
     *   - Chair / operator của meeting (thư ký xuất biên bản sau khi họp xong)
     *   - Admin trang quản trị có Spatie permission `meetings.exportReports`
     *     (không cần là chair/op meeting — thí dụ thư ký văn phòng tổng hợp báo cáo).
     *
     * KHÔNG dùng cho export chi tiết phiếu biểu quyết per-voter (vẫn `operate` only).
     */
    public function exportReports(User $user, Meeting $meeting): bool
    {
        if ($meeting->isChairperson($user) || $meeting->isOperator($user)) {
            return true;
        }

        return $user->can('meetings.exportReports');
    }
}
