<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use App\Modules\Meeting\Enums\MeetingParticipantRoleEnum;
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
        return MeetingParticipant::with(['attendee'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingParticipant $meetingParticipant): MeetingParticipant
    {
        return $meetingParticipant->load(['attendee']);
    }

    public function store(array $validated): MeetingParticipant
    {
        $attendee = MeetingAttendee::query()->findOrFail($validated['meeting_attendee_id']);

        return MeetingParticipant::create([
            'organization_id' => $this->resolveCurrentOrganizationId(),
            'meeting_id' => $validated['meeting_id'],
            'meeting_attendee_id' => $attendee->id,
            'role' => $validated['role'] ?? MeetingParticipantRoleEnum::Delegate->value,
            'display_name' => $attendee->name,
            'position_name' => $attendee->position_name,
            'department_name' => $attendee->department_name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'response_status' => $validated['response_status'] ?? MeetingParticipantResponseStatusEnum::Pending->value,
            'absence_reason' => $validated['absence_reason'] ?? null,
        ])->load(['attendee']);
    }

    public function update(MeetingParticipant $meetingParticipant, array $validated): MeetingParticipant
    {
        $meetingParticipant->update($validated);

        return $meetingParticipant->load(['attendee']);
    }

    public function destroy(MeetingParticipant $meetingParticipant): void
    {
        $meetingParticipant->delete();
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
