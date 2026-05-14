<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MeetingDiscussionRegistration extends TenantModel implements HasMedia
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
        'operator_note',
        'media_id',
        'status',
        'completed_at',
        'highlighted_at',
        'sort_order',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'highlighted_at' => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
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
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_agenda_id'] ?? null, fn ($q, $agendaId) => $q->where('meeting_agenda_id', $agendaId))
            ->when($filters['meeting_participant_id'] ?? null, fn ($q, $participantId) => $q->where('meeting_participant_id', $participantId))
            // Filter `?my=true` / `?mine=1` — chỉ registrations của user đang login.
            // Traverse: meeting_participant_id → MeetingParticipant.meeting_attendee_id → MeetingAttendee.user_id
            ->when(
                filter_var($filters['my'] ?? $filters['mine'] ?? $filters['only_mine'] ?? false, FILTER_VALIDATE_BOOLEAN),
                function ($q) {
                    $userId = auth()->id() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->id();
                    if (! $userId) {
                        $q->whereRaw('1 = 0');  // chưa login → list rỗng

                        return;
                    }
                    $q->whereHas('participant.attendee', fn ($q2) => $q2->where('user_id', $userId));
                }
            )
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
