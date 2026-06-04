<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Scheduling\Enums\ReminderSource;

class ScheduleReminder extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_reminders';

    protected $fillable = [
        'schedule_id', 'minutes_before', 'channels', 'source', 'created_at',
    ];

    protected $casts = [
        'minutes_before' => 'integer',
        'channels'       => 'array',
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

    // ── Compatibility Accessors / Mutators ───────────────────────────────────────

    public function getOffsetMinutesAttribute()
    {
        return $this->minutes_before;
    }

    public function setOffsetMinutesAttribute($value)
    {
        $this->minutes_before = (int)$value;
    }

    public function getReminderTypeAttribute()
    {
        return $this->source ? $this->source->value : 'CUSTOM';
    }

    public function setReminderTypeAttribute($value)
    {
        $value = strtoupper($value);
        $this->source = ReminderSource::tryFrom($value) ?? ReminderSource::CUSTOM;
    }

    // Also support lowercase for source/reminder_type
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
