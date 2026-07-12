<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TaskConfirmed implements ShouldDispatchAfterCommit
{
    public function __construct(public TaskAssignmentItem $item) {}
}
