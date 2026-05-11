<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingAttendanceStatusEnum;
use App\Modules\Meeting\Enums\MeetingCheckinMethodEnum;
use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingAttendanceService
{
    public function stats(array $filters): array
    {
        $base = MeetingAttendance::filter($filters);
        $meetingId = $filters['meeting_id'] ?? null;

        // total_invited = số đại biểu được mời (count meeting_participants).
        // Spec section 6.1: "Tỷ lệ có mặt 42/50" → 42 = present, 50 = participants total.
        $totalInvited = $meetingId
            ? MeetingParticipant::where('meeting_id', $meetingId)
                ->where('organization_id', $this->resolveCurrentOrganizationId())
                ->count()
            : 0;

        return [
            'total_invited' => $totalInvited,
            'total' => (clone $base)->count(),
            'present' => (clone $base)->where('status', 'present')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingAttendance::with('participant.attendee')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingAttendance $meetingAttendance): MeetingAttendance
    {
        return $meetingAttendance->load('participant.attendee');
    }

    public function store(array $validated): MeetingAttendance
    {
        return MeetingAttendance::create([
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
            'checked_in_by' => auth()->id(),
        ])->load('participant.attendee');
    }

    public function update(MeetingAttendance $meetingAttendance, array $validated): MeetingAttendance
    {
        $meetingAttendance->update($validated);

        return $meetingAttendance->load('participant.attendee');
    }

    public function destroy(MeetingAttendance $meetingAttendance): void
    {
        $meetingAttendance->delete();
    }

    /**
     * Operator duyệt điểm danh đại biểu — pending → present.
     * Set checked_in_by = current user (operator), giữ checked_in_at do đại biểu set lúc checkin.
     */
    public function approve(MeetingAttendance $attendance): MeetingAttendance
    {
        if ($attendance->status !== MeetingAttendanceStatusEnum::Pending->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Điểm danh không ở trạng thái chờ duyệt — không thể approve.'],
            ]);
        }

        $attendance->update([
            'status' => MeetingAttendanceStatusEnum::Present->value,
            'checked_in_at' => $attendance->checked_in_at ?? now(),
            'checked_in_by' => auth()->id(),
        ]);

        // Sync RSVP cho participant nếu còn pending → accepted (đại biểu đã có mặt).
        if ($attendance->participant) {
            $this->syncParticipantResponseFromAttendance($attendance->participant, MeetingParticipantResponseStatusEnum::Accepted->value);
        }

        broadcast(new \App\Modules\Meeting\Events\MeetingAttendanceApproved($attendance))->toOthers();

        return $attendance->load('participant.attendee');
    }

    /**
     * Operator từ chối điểm danh — pending → absent.
     */
    public function reject(MeetingAttendance $attendance): MeetingAttendance
    {
        if ($attendance->status !== MeetingAttendanceStatusEnum::Pending->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Điểm danh không ở trạng thái chờ duyệt — không thể reject.'],
            ]);
        }

        $attendance->update([
            'status' => MeetingAttendanceStatusEnum::Absent->value,
            'checked_in_by' => auth()->id(),
        ]);

        // Sync RSVP cho participant nếu còn pending → declined (operator xác nhận vắng).
        if ($attendance->participant) {
            $this->syncParticipantResponseFromAttendance($attendance->participant, MeetingParticipantResponseStatusEnum::Declined->value);
        }

        broadcast(new \App\Modules\Meeting\Events\MeetingAttendanceRejected($attendance))->toOthers();

        return $attendance->load('participant.attendee');
    }

    /**
     * Đại biểu tự điểm danh — status=pending chờ operator duyệt.
     * Idempotent qua unique (meeting_id, meeting_participant_id) — F5/click lần 2 không tạo trùng.
     */
    public function checkin(int $meetingId, string $checkinMethod = MeetingCheckinMethodEnum::Button->value): MeetingAttendance
    {
        $this->ensureAttendanceNotLocked($meetingId);
        $participant = $this->resolveOwnedParticipant($meetingId);

        $attendance = MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meetingId,
                'meeting_participant_id' => $participant->id,
            ],
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'status' => MeetingAttendanceStatusEnum::Pending->value,
                'checkin_method' => $checkinMethod,
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'note' => null,
            ]
        )->load('participant.attendee');

        // Đã checkin → infer RSVP = accepted nếu còn pending (đại biểu k bấm respond riêng).
        $this->syncParticipantResponseFromAttendance($participant, MeetingParticipantResponseStatusEnum::Accepted->value);

        broadcast(new \App\Modules\Meeting\Events\MeetingAttendanceCheckedIn($attendance))->toOthers();

        return $attendance;
    }

    /**
     * Đại biểu điểm danh qua QR token. FE gen QR encode UUID `meeting.checkin_token`,
     * scan → FE call endpoint này. BE auto-resolve meeting + set permissions team theo
     * org của meeting đó (caller có thể chưa setPermissionsTeamId chính xác trên route).
     */
    public function checkinByToken(string $token): MeetingAttendance
    {
        $meeting = \App\Modules\Meeting\Models\Meeting::query()
            ->where('checkin_token', $token)
            ->first();

        if (! $meeting) {
            throw new ModelNotFoundException('QR điểm danh không hợp lệ.');
        }
        if ($meeting->status !== 'published') {
            throw new \Illuminate\Validation\ValidationException(validator([], []), response()->json([
                'success' => false,
                'message' => 'Cuộc họp chưa ban hành — không thể điểm danh.',
            ], 422));
        }

        // Set permissions team theo meeting's organization (override header context).
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId((int) $meeting->organization_id);
        }

        return $this->checkin((int) $meeting->id, MeetingCheckinMethodEnum::Qr->value);
    }

    /**
     * Đại biểu tự báo vắng — status=absent (không cần duyệt).
     * Idempotent: nếu đã có row checkin trước đó, update sang absent + note.
     */
    public function markAbsent(int $meetingId, ?string $note = null): MeetingAttendance
    {
        $this->ensureAttendanceNotLocked($meetingId);
        $participant = $this->resolveOwnedParticipant($meetingId);

        $attendance = MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meetingId,
                'meeting_participant_id' => $participant->id,
            ],
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'status' => MeetingAttendanceStatusEnum::Absent->value,
                // Self-action của đại biểu trên app → method=button (giống checkin).
                // 'manual' dành riêng cho thư ký điểm danh hộ qua manualCheckin().
                'checkin_method' => MeetingCheckinMethodEnum::Button->value,
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'note' => $note,
            ]
        )->load('participant.attendee');

        // Tự báo vắng → infer RSVP = declined nếu còn pending.
        $this->syncParticipantResponseFromAttendance($participant, MeetingParticipantResponseStatusEnum::Declined->value);

        // markAbsent cũng phát event checked-in để Tab điều hành cập nhật list (status=absent → loại khỏi pending list).
        broadcast(new \App\Modules\Meeting\Events\MeetingAttendanceCheckedIn($attendance))->toOthers();

        return $attendance;
    }

    /**
     * Thư ký/operator điểm danh hộ đại biểu (offline scenario: đại biểu không dùng app).
     * Khác với checkin() (đại biểu tự bấm) + checkinByToken() (đại biểu scan QR):
     *   - Caller là thư ký, KHÔNG phải owner của participant.
     *   - Status có thể set là Present (xác nhận có mặt) hoặc Absent (báo vắng hộ).
     *   - checkin_method=manual để báo cáo phân biệt nguồn.
     *   - checked_in_by = auth user (thư ký), không phải participant user.
     *
     * Permission middleware (`meeting-attendances.store`) đã enforce role privileged
     * (chair/op/secretary/admin).
     */
    public function manualCheckin(int $meetingId, int $participantId, string $status, ?string $note = null): MeetingAttendance
    {
        $this->ensureAttendanceNotLocked($meetingId);

        // Validate participant thuộc meeting (chặn cross-meeting bypass).
        $participant = \App\Modules\Meeting\Models\MeetingParticipant::query()
            ->where('id', $participantId)
            ->where('meeting_id', $meetingId)
            ->first();
        if (! $participant) {
            throw new ModelNotFoundException('Đại biểu không thuộc cuộc họp này.');
        }

        if (! in_array($status, [MeetingAttendanceStatusEnum::Present->value, MeetingAttendanceStatusEnum::Absent->value], true)) {
            throw new \Illuminate\Validation\ValidationException(validator([], []), response()->json([
                'success' => false,
                'message' => 'Trạng thái không hợp lệ — chỉ chấp nhận present/absent.',
            ], 422));
        }

        $attendance = MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meetingId,
                'meeting_participant_id' => $participantId,
            ],
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'status' => $status,
                'checkin_method' => MeetingCheckinMethodEnum::Manual->value,
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'note' => $note,
            ]
        )->load('participant.attendee');

        // Manual checkin từ thư ký → infer RSVP cho participant nếu còn pending.
        $rsvp = $status === MeetingAttendanceStatusEnum::Present->value
            ? MeetingParticipantResponseStatusEnum::Accepted->value
            : MeetingParticipantResponseStatusEnum::Declined->value;
        $this->syncParticipantResponseFromAttendance($participant, $rsvp);

        broadcast(new \App\Modules\Meeting\Events\MeetingAttendanceCheckedIn($attendance))->toOthers();

        return $attendance;
    }

    /**
     * Đồng bộ participant.response_status sau khi điểm danh — chỉ overwrite nếu đang Pending,
     * tránh ghi đè quyết định RSVP đại biểu đã chủ động bấm trước đó (Accepted/Declined).
     */
    private function syncParticipantResponseFromAttendance(MeetingParticipant $participant, string $rsvp): void
    {
        if ($participant->response_status !== MeetingParticipantResponseStatusEnum::Pending->value) {
            return;
        }
        $participant->update([
            'response_status' => $rsvp,
            'responded_at' => now(),
        ]);
    }

    /**
     * Chặn self-action (checkin/markAbsent) khi meeting đã khoá điểm danh.
     */
    private function ensureAttendanceNotLocked(int $meetingId): void
    {
        $locked = \App\Modules\Meeting\Models\Meeting::query()
            ->whereKey($meetingId)
            ->value('attendance_locked');

        if ($locked) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'meeting_id' => ['Cuộc họp đã khoá danh sách điểm danh — không thể thao tác.'],
            ]);
        }
    }

    /**
     * Tìm participant của auth user trong meeting — throw 404 nếu user không phải đại biểu.
     */
    private function resolveOwnedParticipant(int $meetingId): MeetingParticipant
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new ModelNotFoundException('Cần đăng nhập để điểm danh.');
        }

        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meetingId)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
        }

        return $participant;
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingAttendance::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
