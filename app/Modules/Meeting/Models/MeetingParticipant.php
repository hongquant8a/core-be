<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingParticipant extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_attendee_id',
        'display_name',
        'position_name',
        'department_name',
        'email',
        'phone',
        'response_status',
        'absence_reason',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function attendee()
    {
        return $this->belongsTo(MeetingAttendee::class, 'meeting_attendee_id');
    }

    /**
     * Bản ghi điểm danh của participant này — 1-1 do unique (meeting_id, meeting_participant_id).
     * Có thể null nếu đại biểu chưa điểm danh.
     */
    public function attendance()
    {
        return $this->hasOne(MeetingAttendance::class, 'meeting_participant_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['response_status'] ?? null, fn ($q, $status) => $q->where('response_status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('display_name', 'like', '%'.$search.'%'))
            ->when($filters['sort_by'] ?? 'id', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'display_name', 'responded_at', 'created_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'id';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'asc');
            });
    }
}
