<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TaskCompleted implements ShouldDispatchAfterCommit
{
    public function __construct(public TaskAssignmentItem $item) {}
}
