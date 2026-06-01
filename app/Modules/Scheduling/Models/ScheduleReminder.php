<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\NotificationSchedule;

class ScheduleReminder extends TenantModel
{
    protected $table = 'schedule_reminders';

    protected $fillable = [
        'organization_id', 'schedule_id', 'notification_schedule_id',
        'reminder_type', 'moment', 'offset_minutes', 'channels',
        'scheduled_at', 'sent_at', 'status', 'message', 'created_by',
        'minutes_before', 'source',
    ];

    protected $casts = [
        'channels'     => 'array',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function getMinutesBeforeAttribute()
    {
        return $this->offset_minutes;
    }

    public function setMinutesBeforeAttribute($value)
    {
        $this->attributes['offset_minutes'] = $value;
    }

    public function getSourceAttribute()
    {
        return strtolower($this->reminder_type ?? 'PRESET');
    }

    public function setSourceAttribute($value)
    {
        $this->attributes['reminder_type'] = strtoupper($value);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function notificationSchedule()
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }
}
