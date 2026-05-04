<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingVoteResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_vote_topic_id',
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

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }
}
