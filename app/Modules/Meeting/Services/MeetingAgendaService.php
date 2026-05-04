<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingAgenda;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingAgendaService
{
    public function index(array $filters, int $limit)
    {
        return MeetingAgenda::with(['parent'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingAgenda $meetingAgenda): MeetingAgenda
    {
        return $meetingAgenda->load(['parent', 'children']);
    }

    public function store(array $validated): MeetingAgenda
    {
        $validated['organization_id'] = $this->resolveCurrentOrganizationId();
        if (! array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id'], $validated['parent_id'] ?? null);
        }

        return MeetingAgenda::create($validated)->load(['parent', 'children']);
    }

    public function update(MeetingAgenda $meetingAgenda, array $validated): MeetingAgenda
    {
        $meetingAgenda->update($validated);

        return $meetingAgenda->load(['parent', 'children']);
    }

    public function destroy(MeetingAgenda $meetingAgenda): void
    {
        $meetingAgenda->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingAgenda::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingAgenda::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $meetingId, ?int $parentId): int
    {
        return ((int) MeetingAgenda::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
            ->where('parent_id', $parentId)
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
