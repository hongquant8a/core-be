<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingPersonalNote;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingPersonalNoteService
{
    public function index(array $filters, int $limit)
    {
        return MeetingPersonalNote::with(['attachments.mediaFile'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingPersonalNote $meetingPersonalNote): MeetingPersonalNote
    {
        return $meetingPersonalNote->load(['attachments.mediaFile']);
    }

    public function store(array $validated): MeetingPersonalNote
    {
        if (! array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id'], $validated['meeting_participant_id']);
        }

        return MeetingPersonalNote::create([
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ])->load(['attachments.mediaFile']);
    }

    public function update(MeetingPersonalNote $meetingPersonalNote, array $validated): MeetingPersonalNote
    {
        $meetingPersonalNote->update($validated);

        return $meetingPersonalNote->load(['attachments.mediaFile']);
    }

    public function destroy(MeetingPersonalNote $meetingPersonalNote): void
    {
        $meetingPersonalNote->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingPersonalNote::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingPersonalNote::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $meetingId, int $meetingParticipantId): int
    {
        return ((int) MeetingPersonalNote::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
            ->where('meeting_participant_id', $meetingParticipantId)
            ->max('sort_order')) + 1;
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
