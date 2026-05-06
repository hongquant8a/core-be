<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingAttendanceStatusEnum;
use App\Modules\Meeting\Enums\MeetingCheckinMethodEnum;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingAttendanceService
{
    public function stats(array $filters): array
    {
        $base = MeetingAttendance::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'present' => (clone $base)->where('status', 'present')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingAttendance::with('participant')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingAttendance $meetingAttendance): MeetingAttendance
    {
        return $meetingAttendance->load('participant');
    }

    public function store(array $validated): MeetingAttendance
    {
        return MeetingAttendance::create([
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
            'checked_in_by' => auth()->id(),
        ])->load('participant');
    }

    public function update(MeetingAttendance $meetingAttendance, array $validated): MeetingAttendance
    {
        $meetingAttendance->update($validated);

        return $meetingAttendance->load('participant');
    }

    public function destroy(MeetingAttendance $meetingAttendance): void
    {
        $meetingAttendance->delete();
    }

    /**
     * Đại biểu tự điểm danh — status=pending chờ operator duyệt.
     * Idempotent qua unique (meeting_id, meeting_participant_id) — F5/click lần 2 không tạo trùng.
     */
    public function checkin(int $meetingId): MeetingAttendance
    {
        $participant = $this->resolveOwnedParticipant($meetingId);

        return MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meetingId,
                'meeting_participant_id' => $participant->id,
            ],
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'status' => MeetingAttendanceStatusEnum::Pending->value,
                'checkin_method' => MeetingCheckinMethodEnum::Button->value,
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'note' => null,
            ]
        )->load('participant');
    }

    /**
     * Đại biểu tự báo vắng — status=absent (không cần duyệt).
     * Idempotent: nếu đã có row checkin trước đó, update sang absent + note.
     */
    public function markAbsent(int $meetingId, ?string $note = null): MeetingAttendance
    {
        $participant = $this->resolveOwnedParticipant($meetingId);

        return MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meetingId,
                'meeting_participant_id' => $participant->id,
            ],
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'status' => MeetingAttendanceStatusEnum::Absent->value,
                'checkin_method' => MeetingCheckinMethodEnum::Manual->value,
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
                'note' => $note,
            ]
        )->load('participant');
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
