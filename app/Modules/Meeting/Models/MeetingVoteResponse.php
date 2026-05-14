<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingVoteResponse extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_vote_topic_id',
        'user_id',
        'meeting_participant_id',
        'option',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
    ];

    public function topic()
    {
        return $this->belongsTo(MeetingVoteTopic::class, 'meeting_vote_topic_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Modules\Core\Models\User::class, 'user_id');
    }

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }
}
