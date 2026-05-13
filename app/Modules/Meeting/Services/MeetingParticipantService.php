<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingParticipantService
{
    public function stats(array $filters): array
    {
        $base = MeetingParticipant::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'accepted' => (clone $base)->where('response_status', MeetingParticipantResponseStatusEnum::Accepted->value)->count(),
            'declined' => (clone $base)->where('response_status', MeetingParticipantResponseStatusEnum::Declined->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingParticipant::with(['attendee.user', 'attendance'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingParticipant $meetingParticipant): MeetingParticipant
    {
        return $meetingParticipant->load(['attendee.user', 'attendance']);
    }

    public function store(array $validated): MeetingParticipant
    {
        $attendee = MeetingAttendee::query()
            ->with('user.profile')
            ->findOrFail($validated['meeting_attendee_id']);

        return MeetingParticipant::create([
            'organization_id' => $this->resolveCurrentOrganizationId(),
            'meeting_id' => $validated['meeting_id'],
            'meeting_attendee_id' => $attendee->id,
            // Snapshot per spec: copy data lúc tạo participant — không thay đổi khi user đổi profile sau.
            'display_name' => $attendee->user?->name,
            'position_name' => $attendee->position_name,
            'department_name' => $attendee->department_name,
            'email' => $attendee->user?->email,
            'phone' => $attendee->user?->profile?->phone,
            'response_status' => $validated['response_status'] ?? MeetingParticipantResponseStatusEnum::Pending->value,
            'absence_reason' => $validated['absence_reason'] ?? null,
        ])->load(['attendee.user', 'attendance']);
    }

    public function update(MeetingParticipant $meetingParticipant, array $validated): MeetingParticipant
    {
        $meetingParticipant->update($validated);

        return $meetingParticipant->load(['attendee.user', 'attendance']);
    }

    public function destroy(MeetingParticipant $meetingParticipant): void
    {
        $meetingParticipant->delete();
    }

    /**
     * Đại biểu tự xác nhận / từ chối tham gia (response invitation).
     * Ownership check: chỉ owner đại biểu (auth user là attendee.user_id của participant này).
     */
    public function respond(MeetingParticipant $meetingParticipant, array $validated): MeetingParticipant
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new ModelNotFoundException('Cần đăng nhập để phản hồi mời họp.');
        }

        $meetingParticipant->loadMissing(['attendee', 'meeting']);
        if ((int) ($meetingParticipant->attendee?->user_id ?? 0) !== (int) $userId) {
            throw new ModelNotFoundException('Bạn không phải đại biểu này.');
        }

        // Chỉ cho phản hồi (xác nhận tham gia / báo vắng) TRƯỚC khi cuộc họp bắt đầu.
        // Sau start_time, đại biểu chỉ có thể điểm danh / báo vắng tại chỗ qua endpoint attendance.
        $startTime = $meetingParticipant->meeting?->start_time;
        if ($startTime && now()->gte($startTime)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'response_status' => ['Cuộc họp đã bắt đầu — không thể cập nhật xác nhận tham gia / báo vắng nữa.'],
            ]);
        }

        $meetingParticipant->update([
            'response_status' => $validated['response_status'],
            'absence_reason' => $validated['response_status'] === MeetingParticipantResponseStatusEnum::Declined->value
                ? ($validated['absence_reason'] ?? null)
                : null,
            'responded_at' => now(),
        ]);

        return $meetingParticipant->load(['attendee.user', 'attendance']);
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingParticipant::query()
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
