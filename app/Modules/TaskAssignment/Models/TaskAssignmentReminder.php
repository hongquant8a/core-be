<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentReminder extends Model
{
    protected $table = 'task_assignment_reminders';

    protected $fillable = [
        'task_assignment_item_id',
        'notification_schedule_id',
        'moment',
        'offset_minutes',
        'channels',
        'source',
        'remind_at',
        'status',
        'fired_at',
    ];

    protected $casts = [
        'offset_minutes' => 'integer',
        'channels'       => 'array',
        'remind_at'      => 'datetime',
        'fired_at'       => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function schedule()
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }
}
