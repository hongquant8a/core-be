<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Có trao đổi / ghi chú mới trên công việc → báo cho những người liên quan còn
 * lại (trừ chính tác giả).
 *
 * Đây là kênh trao đổi chính trong công việc mà trước đây im lặng hoàn toàn:
 * ai hỏi trong ghi chú thì người kia chỉ biết khi tự mở công việc ra xem.
 *
 * Fire ở TaskAssignmentNoteService::store.
 */
class NoteAdded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItemNote $note,
    ) {}
}
