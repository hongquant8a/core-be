<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingInvitation extends Model
{
    protected $table = 'meeting_invitations';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_participant_id',
        'send_type',
        'scheduled_at',
        'sent_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }
}
