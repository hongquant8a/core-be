<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingVoteTopicStatusEnum;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingVoteTopicService
{
    public function stats(array $filters): array
    {
        $base = MeetingVoteTopic::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'opened' => (clone $base)->where('status', 'opened')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingVoteTopic::with(['creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingVoteTopic $meetingVoteTopic): MeetingVoteTopic
    {
        return $meetingVoteTopic->load(['creator', 'editor']);
    }

    public function store(array $validated): MeetingVoteTopic
    {
        if (! array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = ((int) MeetingVoteTopic::query()
                ->where('organization_id', $this->resolveCurrentOrganizationId())
                ->where('meeting_id', $validated['meeting_id'])
                ->max('sort_order')) + 1;
        }

        return MeetingVoteTopic::create([
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
            'status' => $validated['status'] ?? MeetingVoteTopicStatusEnum::Draft->value,
        ])->load(['creator', 'editor']);
    }

    public function update(MeetingVoteTopic $meetingVoteTopic, array $validated): MeetingVoteTopic
    {
        $meetingVoteTopic->update($validated);

        return $meetingVoteTopic->load(['creator', 'editor']);
    }

    public function destroy(MeetingVoteTopic $meetingVoteTopic): void
    {
        $meetingVoteTopic->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingVoteTopic::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function open(MeetingVoteTopic $meetingVoteTopic): MeetingVoteTopic
    {
        $meetingVoteTopic->update([
            'status' => MeetingVoteTopicStatusEnum::Opened->value,
            'opened_at' => now(),
            'closed_at' => null,
        ]);

        return $meetingVoteTopic->load(['creator', 'editor']);
    }

    public function close(MeetingVoteTopic $meetingVoteTopic): MeetingVoteTopic
    {
        $meetingVoteTopic->update([
            'status' => MeetingVoteTopicStatusEnum::Closed->value,
            'closed_at' => now(),
        ]);

        return $meetingVoteTopic->load(['creator', 'editor']);
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingVoteTopic::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
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
