<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use App\Modules\Meeting\Models\Meeting;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingService
{
    public function publicIndex(array $filters, int $limit)
    {
        $publicFilters = [
            ...$filters,
            'is_public' => true,
            'status' => MeetingStatusEnum::Published->value,
        ];

        return Meeting::with(['meetingType', 'meetingLocation'])
            ->filter($publicFilters)
            ->paginate($limit);
    }

    public function publicShow(Meeting $meeting): Meeting
    {
        if (! $meeting->is_public || $meeting->status !== MeetingStatusEnum::Published->value) {
            throw new ModelNotFoundException('Không tìm thấy cuộc họp công khai.');
        }

        $meeting->increment('view_count');

        return $meeting->fresh()->load(['meetingType', 'meetingLocation']);
    }

    public function stats(array $filters): array
    {
        $base = Meeting::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->where('status', MeetingStatusEnum::Published->value)->count(),
            'draft' => (clone $base)->where('status', MeetingStatusEnum::Draft->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Meeting::with(['meetingType', 'meetingLocation', 'creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Meeting $meeting): Meeting
    {
        return $meeting->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
    }

    public function store(array $validated): Meeting
    {
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return Meeting::create($payload)->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
    }

    public function update(Meeting $meeting, array $validated): Meeting
    {
        $meeting->update($validated);

        return $meeting->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
    }

    public function destroy(Meeting $meeting): void
    {
        $meeting->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(Meeting $meeting, string $status): Meeting
    {
        $meeting->update(['status' => $status]);

        return $meeting->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
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
