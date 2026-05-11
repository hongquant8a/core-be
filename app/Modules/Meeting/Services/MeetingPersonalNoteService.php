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

        // Verify user có access tới meeting: chair / operator / participant.
        $meeting = \App\Modules\Meeting\Models\Meeting::with(['chairperson', 'operator'])
            ->find($meetingId);
        if (! $meeting) {
            throw new ModelNotFoundException('Cuộc họp không tồn tại.');
        }

        $isChair = (int) ($meeting->chairperson?->user_id ?? 0) === $userId;
        $isOperator = (int) ($meeting->operator?->user_id ?? 0) === $userId;
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meetingId)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $isChair && ! $isOperator && ! $participant) {
            throw new ModelNotFoundException('Bạn không có quyền ghi chú cho cuộc họp này.');
        }

        $payload = [
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            // participant_id chỉ set nếu user có participant row (chair/op không bắt buộc).
            'meeting_participant_id' => $participant?->id,
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($meetingId, $userId),
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
     * Base query auto-scope theo organization + ownership theo user_id trực tiếp
     * (chair/op cũng có note nhưng không có participant row).
     */
    private function ownedQuery(): Builder
    {
        return MeetingPersonalNote::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('user_id', $this->resolveCurrentUserId());
    }

    /**
     * Throw 404 nếu note không thuộc user hiện tại (tránh leak ID-existence).
     */
    private function ensureOwned(MeetingPersonalNote $note): void
    {
        if ((int) $note->user_id !== $this->resolveCurrentUserId()) {
            throw new ModelNotFoundException('Không tìm thấy ghi chú.');
        }
    }

    private function nextSortOrder(int $meetingId, int $userId): int
    {
        return ((int) MeetingPersonalNote::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
            ->where('user_id', $userId)
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
