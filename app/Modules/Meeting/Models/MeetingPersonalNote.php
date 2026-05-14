<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingPersonalNote extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'user_id',
        'meeting_participant_id',
        'content',
        'sort_order',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Modules\Core\Models\User::class, 'user_id');
    }

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
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_participant_id'] ?? null, fn ($q, $participantId) => $q->where('meeting_participant_id', $participantId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');
    }
}
