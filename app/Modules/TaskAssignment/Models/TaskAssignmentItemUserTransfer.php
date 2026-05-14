<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;

class TaskAssignmentItemUserTransfer extends TenantModel
{
    protected $table = 'task_assignment_item_user_transfers';

    protected $fillable = [
        'task_assignment_item_id',
        'from_user_id',
        'to_user_id',
        'transferred_by_user_id',
        'transferred_department_id',
        'transferred_at',
        'note',
        'organization_id',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }

    public function department()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'transferred_department_id');
    }
}
