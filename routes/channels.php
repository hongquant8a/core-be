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
 * Allow nếu user là chair / operator / participant của meeting (FK match).
 * Spatie role check không apply ở đây vì:
 *  1) Endpoint /api/broadcasting/auth có thể không có header X-Organization-Id để
 *     setPermissionsTeamId (team mode) — Spatie role check sẽ luôn false.
 *  2) Vai trò "Thư ký họp" thực tế là operator của meeting đó (đã có FK match).
 *  3) Super Admin / Admin nếu cần subscribe phải tự thêm vào chair/op/participant.
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

    return $meeting->participants()
        ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
