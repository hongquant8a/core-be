<?php

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Định nghĩa quyền truy cập channel cho Reverb. Auth callback chạy khi FE
| subscribe channel qua Echo — return true => allow, false/null => 403.
*/

/**
 * Private channel cho 1 cuộc họp — broadcast các event runtime (vote, highlight,
 * discussion, attendance...) cho mọi người tham gia.
 *
 * Allow nếu:
 *  1) User là chair / operator / participant của meeting (FK match), HOẶC
 *  2) User có Spatie role privileged (Super Admin / Admin / Quản trị) trong org
 *     của meeting đó — set permissions team từ meeting.organization_id để Spatie
 *     check chính xác (không phụ thuộc header X-Organization-Id của request).
 */
Broadcast::channel('meeting.{meetingId}', function ($user, int $meetingId) {
    $meeting = Meeting::with(['chairperson', 'operator'])->find($meetingId);
    if (! $meeting) {
        return false;
    }

    $userId = (int) $user->id;
    $isChair = (int) ($meeting->chairperson?->user_id ?? 0) === $userId;
    $isOperator = (int) ($meeting->operator?->user_id ?? 0) === $userId;

    $participant = MeetingParticipant::where('meeting_id', $meeting->id)
        ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
        ->with('attendance')
        ->first();

    $isParticipant = $participant !== null;

    // is_attendance_confirmed: đại biểu đã được xác nhận điểm danh (attendance present).
    $isAttendanceConfirmed = $participant && $participant->attendance?->status === 'present';

    if ($isChair || $isOperator || $isParticipant) {
        return [
            'user_id'                  => $userId,
            'participant_id'           => $participant?->id,
            'is_attendance_confirmed'  => $isAttendanceConfirmed,
        ];
    }

    // Spatie role privileged trong org của meeting (Super Admin / Admin / Quản trị).
    if (function_exists('setPermissionsTeamId')) {
        setPermissionsTeamId((int) $meeting->organization_id);
    }
    if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị'])) {
        return [
            'user_id'                 => $userId,
            'participant_id'          => $participant?->id,
            'is_attendance_confirmed' => $isAttendanceConfirmed,
        ];
    }

    return false;
});
