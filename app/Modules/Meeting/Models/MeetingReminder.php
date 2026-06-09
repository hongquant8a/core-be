<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\TenantModel;

class MeetingReminder extends TenantModel
{
    protected $table = 'meeting_reminders';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'reminder_type',
        'notification_schedule_id',
        'moment',
        'offset_minutes',
        'channels',
        'source',
        'remind_at',
        'scheduled_at',
        'sent_at',
        'fired_at',
        'message',
        'status',
        'created_by',
    ];

    protected $casts = [
        'offset_minutes' => 'integer',
        'channels'       => 'array',
        'scheduled_at'   => 'datetime',
        'sent_at'        => 'datetime',
        'fired_at'       => 'datetime',
        'remind_at'      => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function schedule()
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }
}
