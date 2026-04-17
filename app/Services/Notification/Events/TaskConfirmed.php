<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;

class TaskConfirmed
{
    public function __construct(public TaskAssignmentItem $item) {}
}
