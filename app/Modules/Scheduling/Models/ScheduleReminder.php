<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleReminder extends Model
{
    use HasFactory;

    protected $table = 'schedule_reminders';

    protected $fillable = [
        'schedule_id',
        'reminder_preset_id',
        'source', // preset, custom
        'moment', // before, on, after
        'offset_minutes',
        'minutes_before', // Virtual field mapped to offset_minutes
        'remind_at',
        'channels',
        'status', // 0=PENDING, 1=SENT, 2=FAILED
        'sent_at',
    ];

    protected $casts = [
        'schedule_id' => 'integer',
        'reminder_preset_id' => 'integer',
        'offset_minutes' => 'integer',
        'remind_at' => 'datetime',
        'channels' => 'array',
        'status' => 'integer',
        'sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function (ScheduleReminder $reminder) {
            if ($reminder->schedule) {
                $scheduler = app(\App\Services\Notification\Services\ScheduleReminderScheduler::class);
                $eventDatetime = $scheduler->getEventDatetime($reminder->schedule);
                $reminder->remind_at = $eventDatetime->copy()->subMinutes($reminder->offset_minutes);
            } else {
                $reminder->remind_at = $reminder->remind_at ?? now();
            }
        });
    }

    public function getMinutesBeforeAttribute(): int
    {
        return $this->offset_minutes;
    }

    public function setMinutesBeforeAttribute($value): void
    {
        $this->attributes['offset_minutes'] = $value;
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function preset()
    {
        return $this->belongsTo(ReminderPreset::class, 'reminder_preset_id');
    }
}
