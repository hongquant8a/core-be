<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Scheduling\Enums\ReminderSource;

class ScheduleReminder extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_reminders';

    protected $fillable = [
        'schedule_id', 'reminder_type', 'moment', 'offset_minutes', 'remind_at', 'channels',
        'notification_schedule_id', 'status', 'fired_at', 'source', 'created_at',
    ];

    protected $casts = [
        'offset_minutes' => 'integer',
        'channels'       => 'array',
        'remind_at'      => 'datetime',
        'fired_at'       => 'datetime',
        'created_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ScheduleReminder $reminder) {
            if (is_null($reminder->created_at)) {
                $reminder->created_at = now();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function notificationSchedule()
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }

    // ── Compatibility ──────────────────────────────────────────────────────

    /** @deprecated Dùng moment thay thế. */
    public function getTriggerAttribute()
    {
        return $this->moment;
    }

    /** @deprecated Dùng offset_minutes thay thế. */
    public function getMinutesBeforeAttribute()
    {
        return $this->offset_minutes;
    }

    public function getReminderTypeAttribute($value): string
    {
        return $value ?: ($this->moment === null ? 'instant' : 'scheduled');
    }

    public function setReminderTypeAttribute($value): void
    {
        $this->attributes['reminder_type'] = $value;

        if ($value === 'instant') {
            $this->moment = null;
        }
    }

    public function getSourceAttribute($value)
    {
        return $value;
    }

    public function setSourceAttribute($value)
    {
        if (is_string($value)) {
            $value = strtoupper($value);
            $this->attributes['source'] = ReminderSource::tryFrom($value) ? $value : 'CUSTOM';
        } elseif ($value instanceof ReminderSource) {
            $this->attributes['source'] = $value->value;
        }
    }
}
