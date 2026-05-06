<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingPersonalNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Ghi chú cá nhân — chỉ người tạo (đại biểu) thấy/sửa/xóa được note của chính mình.
 * Ownership scope qua participant.attendee.user_id == auth user. Không có Policy class
 * riêng — logic gói gọn trong service (consistent với pattern các module khác).
 */
class MeetingPersonalNoteService
{
    public function index(array $filters, int $limit)
    {
        return $this->ownedQuery()
            ->with(['attachments.mediaFile'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingPersonalNote $meetingPersonalNote): MeetingPersonalNote
    {
        $this->ensureOwned($meetingPersonalNote);

        return $meetingPersonalNote->load(['attachments.mediaFile']);
    }

    public function store(array $validated): MeetingPersonalNote
    {
        $userId = $this->resolveCurrentUserId();
        $meetingId = (int) $validated['meeting_id'];

        // Auto-derive participant_id từ user — đảm bảo user tạo note cho chính mình.
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meetingId)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
        }

        $payload = [
            'meeting_id' => $meetingId,
            'meeting_participant_id' => $participant->id,
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($meetingId, $participant->id),
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return MeetingPersonalNote::create($payload)->load(['attachments.mediaFile']);
    }

    public function update(MeetingPersonalNote $meetingPersonalNote, array $validated): MeetingPersonalNote
    {
        $this->ensureOwned($meetingPersonalNote);
        $meetingPersonalNote->update($validated);

        return $meetingPersonalNote->load(['attachments.mediaFile']);
    }

    public function destroy(MeetingPersonalNote $meetingPersonalNote): void
    {
        $this->ensureOwned($meetingPersonalNote);
        $meetingPersonalNote->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        $this->ownedQuery()->whereIn('id', $ids)->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $this->ownedQuery()
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    /**
     * Base query auto-scope theo organization + ownership của auth user.
     */
    private function ownedQuery(): Builder
    {
        $userId = $this->resolveCurrentUserId();

        return MeetingPersonalNote::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereHas('participant.attendee', fn ($q) => $q->where('user_id', $userId));
    }

    /**
     * Throw 404 nếu note không thuộc user hiện tại (tránh leak ID-existence).
     */
    private function ensureOwned(MeetingPersonalNote $note): void
    {
        $userId = $this->resolveCurrentUserId();
        $note->loadMissing('participant.attendee');

        if ((int) ($note->participant?->attendee?->user_id ?? 0) !== (int) $userId) {
            throw new ModelNotFoundException('Không tìm thấy ghi chú.');
        }
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

    private function resolveCurrentUserId(): int
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new ModelNotFoundException('Cần đăng nhập để truy cập ghi chú cá nhân.');
        }

        return (int) $userId;
    }
}
