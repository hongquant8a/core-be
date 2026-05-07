<?php

use App\Modules\Meeting\Models\Meeting;
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
 * Allow nếu user là chair / operator / participant của meeting, hoặc có role
 * Super Admin / Admin / Thư ký họp (org-wide).
 */
Broadcast::channel('meeting.{meetingId}', function ($user, int $meetingId) {
    $meeting = Meeting::with(['chairperson', 'operator'])->find($meetingId);
    if (! $meeting) {
        return false;
    }

    if ((int) ($meeting->chairperson?->user_id ?? 0) === (int) $user->id) {
        return true;
    }
    if ((int) ($meeting->operator?->user_id ?? 0) === (int) $user->id) {
        return true;
    }

    if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin', 'Thư ký họp'])) {
        return true;
    }

    return $meeting->participants()
        ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
