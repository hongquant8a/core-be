<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MeetingDiscussionRegistration extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_agenda_id',
        'meeting_participant_id',
        'type',
        'content',
        'media_id',
        'status',
        'called_at',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }

    public function agenda()
    {
        return $this->belongsTo(MeetingAgenda::class, 'meeting_agenda_id');
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('meeting-discussion-attachments');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_agenda_id'] ?? null, fn ($q, $agendaId) => $q->where('meeting_agenda_id', $agendaId))
            ->when($filters['meeting_participant_id'] ?? null, fn ($q, $participantId) => $q->where('meeting_participant_id', $participantId))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
