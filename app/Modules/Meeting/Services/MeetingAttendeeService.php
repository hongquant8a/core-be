<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use App\Modules\Meeting\Models\MeetingAttendee;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingAttendeeService
{
    public function stats(array $filters): array
    {
        $base = MeetingAttendee::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', MeetingCatalogStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', MeetingCatalogStatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingAttendee::with(['group', 'creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingAttendee $meetingAttendee): MeetingAttendee
    {
        return $meetingAttendee->load(['group', 'creator', 'editor']);
    }

    public function store(array $validated): MeetingAttendee
    {
        $payload = [...$validated, 'organization_id' => $this->resolveCurrentOrganizationId()];

        return MeetingAttendee::create($payload)->load(['group', 'creator', 'editor']);
    }

    public function update(MeetingAttendee $meetingAttendee, array $validated): MeetingAttendee
    {
        $meetingAttendee->update($validated);

        return $meetingAttendee->load(['group', 'creator', 'editor']);
    }

    public function destroy(MeetingAttendee $meetingAttendee): void
    {
        $meetingAttendee->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingAttendee::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        MeetingAttendee::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(MeetingAttendee $meetingAttendee, string $status): MeetingAttendee
    {
        $meetingAttendee->update(['status' => $status]);

        return $meetingAttendee->load(['group', 'creator', 'editor']);
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
