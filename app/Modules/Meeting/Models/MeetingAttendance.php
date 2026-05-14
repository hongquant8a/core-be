<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingAttendance extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_participant_id',
        'status',
        'checkin_method',
        'checked_in_at',
        'checked_in_by',
        'note',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function participant()
    {
        return $this->belongsTo(MeetingParticipant::class, 'meeting_participant_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('participant', fn ($participantQ) => $participantQ->where('display_name', 'like', '%'.$search.'%'));
            })
            ->when($filters['sort_by'] ?? 'id', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'checked_in_at', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'id';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'asc');
            });
    }
}
