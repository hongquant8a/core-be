<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $table = 'reminders';

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'organization_id',
        'reminder_type',
        'source',
        'notification_schedule_id',
        'moment',
        'offset_minutes',
        'remind_at',
        'channels',
        'status',
        'fired_at',
        'message',
        'created_by',
    ];

    protected $casts = [
        'offset_minutes' => 'integer',
        'channels'       => 'array',
        'remind_at'      => 'datetime',
        'fired_at'       => 'datetime',
    ];

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function notificationSchedule(): BelongsTo
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }
}
