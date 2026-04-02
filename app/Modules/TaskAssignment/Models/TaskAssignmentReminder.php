<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentReminder extends Model
{
    protected $table = 'task_assignment_reminders';

    protected $fillable = [
        'task_assignment_item_id',
        'remind_at',
        'sent_at',
        'channel',
        'recipient_department_id',
        'recipient_user_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function recipientDepartment()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'recipient_department_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
