<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingPersonalNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_participant_id',
        'content',
        'sort_order',
    ];

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }

    public function attachments()
    {
        return $this->hasMany(MeetingPersonalNoteAttachment::class, 'meeting_personal_note_id')->orderBy('sort_order');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_participant_id'] ?? null, fn ($q, $participantId) => $q->where('meeting_participant_id', $participantId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');
    }
}
