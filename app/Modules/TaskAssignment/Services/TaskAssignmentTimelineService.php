<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemUserTransfer;
use App\Modules\TaskAssignment\Resources\NoteResource;
use App\Modules\TaskAssignment\Resources\TransferResource;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskAssignmentTimelineService
{
    use FormatsUserSummary;

    /**
     * Gom notes + transfers thành unified timeline, sort by time ASC, paginate thủ công.
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

        // 3. Merge + sort ASC by timestamp
        $merged = $notes->concat($transfers)
            ->sortBy('timestamp')
            ->values();

        // 4. Manual paginate
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
