<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_attendee_id',
        'role',
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

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['response_status'] ?? null, fn ($q, $status) => $q->where('response_status', $status))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('display_name', 'like', '%'.$search.'%'))
            ->when($filters['sort_by'] ?? 'id', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'display_name', 'responded_at', 'created_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'id';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }
}
