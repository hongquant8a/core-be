<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TaskAssignmentItemUser extends Pivot
{
    protected $table = 'task_assignment_item_user';

    public $incrementing = true;

    protected $casts = [
        'assigned_at'  => 'datetime',
        'accepted_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];
}
