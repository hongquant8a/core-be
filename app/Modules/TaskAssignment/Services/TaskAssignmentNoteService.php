<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;

class TaskAssignmentNoteService
{
    /**
     * Danh sách ghi chú theo công việc, order created_at ASC (timeline).
     */
    public function index(int $itemId, int $limit)
    {
        return TaskAssignmentItemNote::where('task_assignment_item_id', $itemId)
            ->with('author')
            ->orderBy('created_at', 'asc')
            ->paginate($limit);
    }

    /**
     * Thêm ghi chú cho công việc (append-only).
     *
     * author_role: người có quyền giao việc (`task-assignment-documents.storeItem`)
     * → 'manager', ngược lại → 'assignee'.
     */
    public function store(TaskAssignmentItem $item, array $validated): TaskAssignmentItemNote
    {
        $user = auth()->user();

        $authorRole = $user->can('task-assignment-documents.storeItem')
            ? 'manager'
            : 'assignee';

        $note = TaskAssignmentItemNote::create([
            'task_assignment_item_id' => $item->id,
            'author_user_id' => $user->id,
            'author_role' => $authorRole,
            'content' => $validated['content'],
            'organization_id' => $item->organization_id,
        ]);

        return $note->load('author');
    }
}
