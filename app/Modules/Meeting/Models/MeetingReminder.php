<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingReminder extends Model
{
    protected $table = 'meeting_reminders';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'reminder_type',
        'scheduled_at',
        'sent_at',
        'message',
        'status',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
