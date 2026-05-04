<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingAttendance;
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
