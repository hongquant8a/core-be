<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemUserTransfer;
use App\Modules\TaskAssignment\Resources\ExtensionResource;
use App\Modules\TaskAssignment\Resources\NoteResource;
use App\Modules\TaskAssignment\Resources\TransferResource;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskAssignmentTimelineService
{
    use FormatsUserSummary;

    /**
     * Gom notes + transfers + gia hạn thành unified timeline, sort by time ASC,
     * paginate thủ công.
     */
    public function timeline(int $itemId, int $limit, int $page = 1): LengthAwarePaginator
    {
        // 1. Lấy tất cả notes
        $notes = TaskAssignmentItemNote::where('task_assignment_item_id', $itemId)
            ->with('author')
            ->get()
            ->map(fn ($note) => [
                'type' => 'note',
                'id' => $note->id,
                'timestamp' => $note->created_at,
                'actor' => $this->formatUserSummary($note->author),
                'data' => (new NoteResource($note))->resolve(),
            ]);

        // 2. Lấy tất cả transfers
        $transfers = TaskAssignmentItemUserTransfer::where('task_assignment_item_id', $itemId)
            ->with(['fromUser', 'toUser', 'transferredBy', 'department'])
            ->get()
            ->map(fn ($transfer) => [
                'type' => 'transfer',
                'id' => $transfer->id,
                'timestamp' => $transfer->transferred_at,
                'actor' => $this->formatUserSummary($transfer->transferredBy),
                'data' => (new TransferResource($transfer))->resolve(),
            ]);

        // 3. Lấy các lần xin gia hạn.
        //
        // Mỗi yêu cầu sinh HAI mốc chứ không phải một: mốc gửi (`created_at`) và
        // mốc duyệt/từ chối (`reviewed_at`). Gộp một mốc thì lần duyệt hôm nay lại
        // hiện ở vị trí của ngày gửi tuần trước — dòng thời gian phải phản ánh
        // đúng thứ tự sự việc.
        $extensions = TaskAssignmentItemExtension::where('task_assignment_item_id', $itemId)
            ->with(['requestedBy', 'reviewedBy'])
            ->get();

        $extensionRequested = $extensions->map(fn ($extension) => [
            'type' => 'extension_requested',
            'id' => $extension->id,
            'timestamp' => $extension->created_at,
            'actor' => $this->formatUserSummary($extension->requestedBy),
            'data' => (new ExtensionResource($extension))->resolve(),
        ]);

        $extensionReviewed = $extensions
            ->filter(fn ($extension) => $extension->reviewed_at !== null)
            ->map(fn ($extension) => [
                'type' => 'extension_reviewed',
                'id' => $extension->id,
                'timestamp' => $extension->reviewed_at,
                'actor' => $this->formatUserSummary($extension->reviewedBy),
                'data' => (new ExtensionResource($extension))->resolve(),
            ]);

        // 4. Merge + sort ASC by timestamp
        $merged = $notes->concat($transfers)
            ->concat($extensionRequested)
            ->concat($extensionReviewed)
            ->sortBy('timestamp')
            ->values();

        // 5. Manual paginate
        $total = $merged->count();
        $offset = ($page - 1) * $limit;
        $items = $merged->slice($offset, $limit)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $limit,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
