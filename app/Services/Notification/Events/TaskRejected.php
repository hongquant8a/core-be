<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Người giao việc từ chối (trả lại) công việc đang chờ duyệt → báo cho những
 * người thực hiện biết để làm lại. Fire ở TaskAssignmentItemService::reject.
 */
class TaskRejected implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItem $item,
        public string $reason,
    ) {}
}
