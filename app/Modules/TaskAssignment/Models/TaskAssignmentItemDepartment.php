<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TaskAssignmentItemDepartment extends Pivot
{
    protected $table = 'task_assignment_item_department';

    public $incrementing = true;
}
