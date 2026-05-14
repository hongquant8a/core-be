<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;

class MeetingInvitation extends TenantModel
{
    protected $table = 'meeting_invitations';

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_participant_id',
        'meeting_attendee_id',
        'meeting_guest_id',
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

    public function attendee()
    {
        return $this->belongsTo(MeetingAttendee::class, 'meeting_attendee_id');
    }

    public function guest()
    {
        return $this->belongsTo(MeetingGuest::class, 'meeting_guest_id');
    }
}
