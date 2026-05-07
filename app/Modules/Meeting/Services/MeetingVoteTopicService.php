<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingVoteTopicService
{
    public function stats(array $filters): array
    {
        $base = MeetingVoteTopic::filter($filters);

        // Phase derive từ opened_at + closed_at + duration_minutes (timeout).
        // Reuse filter scope phase logic — đếm số lượng theo từng phase.
        $ids = (clone $base)->pluck('id')->all();

        return [
            'total' => count($ids),
            'draft' => MeetingVoteTopic::query()->whereIn('id', $ids)->filter(['status' => 'draft'])->count(),
            'opened' => MeetingVoteTopic::query()->whereIn('id', $ids)->filter(['status' => 'opened'])->count(),
            'closed' => MeetingVoteTopic::query()->whereIn('id', $ids)->filter(['status' => 'closed'])->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingVoteTopic::with(['creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingVoteTopic $meetingVoteTopic): MeetingVoteTopic
    {
        return $meetingVoteTopic->load(['creator.media', 'editor.media']);
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
        ])->load(['creator.media', 'editor.media']);
    }

    public function update(MeetingVoteTopic $meetingVoteTopic, array $validated): MeetingVoteTopic
    {
        $meetingVoteTopic->update($validated);

        return $meetingVoteTopic->load(['creator.media', 'editor.media']);
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

    public function open(MeetingVoteTopic $meetingVoteTopic, array $payload = []): MeetingVoteTopic
    {
        $update = [
            'opened_at' => now(),
            'closed_at' => null,
        ];
        if (array_key_exists('description', $payload) && $payload['description'] !== null) {
            $update['description'] = $payload['description'];
        }
        if (array_key_exists('duration_minutes', $payload) && $payload['duration_minutes'] !== null) {
            $update['duration_minutes'] = (int) $payload['duration_minutes'];
        }

        $meetingVoteTopic->update($update);

        broadcast(new \App\Modules\Meeting\Events\MeetingVoteTopicOpened($meetingVoteTopic))->toOthers();

        return $meetingVoteTopic->load(['creator.media', 'editor.media']);
    }

    public function close(MeetingVoteTopic $meetingVoteTopic): MeetingVoteTopic
    {
        $meetingVoteTopic->update([
            'closed_at' => now(),
        ]);

        broadcast(new \App\Modules\Meeting\Events\MeetingVoteTopicClosed($meetingVoteTopic))->toOthers();

        return $meetingVoteTopic->load(['creator.media', 'editor.media']);
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
