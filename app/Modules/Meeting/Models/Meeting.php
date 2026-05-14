<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends TenantModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'meeting_type_id',
        'meeting_location_id',
        'chairperson_meeting_attendee_id',
        'operator_meeting_attendee_id',
        'title',
        'is_public',
        'content',
        'start_time',
        'end_time',
        'attendance_open_at',
        'attendance_close_at',
        'status',
        'view_count',
        'published_at',
        'attendance_locked',
        'current_meeting_agenda_id',
        'current_meeting_discussion_registration_id',
        'created_by',
        'updated_by',
        'checkin_token',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'meeting_type_id' => 'integer',
        'meeting_location_id' => 'integer',
        'is_public' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendance_open_at' => 'datetime',
        'attendance_close_at' => 'datetime',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'attendance_locked' => 'boolean',
        'current_meeting_agenda_id' => 'integer',
        'current_meeting_discussion_registration_id' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (Meeting $meeting) {
            $meeting->created_by = auth()->id();
            $meeting->updated_by = auth()->id();
            // Auto-generate checkin_token UUID nếu chưa set — dùng cho QR điểm danh.
            if (empty($meeting->checkin_token)) {
                $meeting->checkin_token = (string) \Illuminate\Support\Str::uuid();
            }
        });
        static::updating(fn (Meeting $meeting) => $meeting->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function meetingType()
    {
        return $this->belongsTo(MeetingType::class, 'meeting_type_id');
    }

    public function meetingLocation()
    {
        return $this->belongsTo(MeetingLocation::class, 'meeting_location_id');
    }

    public function chairperson()
    {
        return $this->belongsTo(MeetingAttendee::class, 'chairperson_meeting_attendee_id');
    }

    public function operator()
    {
        return $this->belongsTo(MeetingAttendee::class, 'operator_meeting_attendee_id');
    }

    public function participants()
    {
        return $this->hasMany(MeetingParticipant::class, 'meeting_id');
    }

    /**
     * User là chủ trì của meeting? Match qua chairperson_meeting_attendee_id → attendee.user_id.
     */
    public function isChairperson(\App\Modules\Core\Models\User $user): bool
    {
        if ($this->chairperson_meeting_attendee_id === null) {
            return false;
        }

        return $this->chairperson?->user_id === $user->id;
    }

    /**
     * User là thư ký/operator của meeting?
     */
    public function isOperator(\App\Modules\Core\Models\User $user): bool
    {
        if ($this->operator_meeting_attendee_id === null) {
            return false;
        }

        return $this->operator?->user_id === $user->id;
    }

    /**
     * User là đại biểu (participant đã được mời) của meeting? Check qua participant.attendee.user_id.
     */
    public function isParticipant(\App\Modules\Core\Models\User $user): bool
    {
        return $this->participants()
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    /**
     * Trả về vai trò của user trong meeting: 'chairperson' | 'operator' | 'participant' | null.
     * Ưu tiên chair > operator > participant.
     */
    public function userMeetingRole(\App\Modules\Core\Models\User $user): ?string
    {
        if ($this->isChairperson($user)) {
            return 'chairperson';
        }
        if ($this->isOperator($user)) {
            return 'operator';
        }
        if ($this->isParticipant($user)) {
            return 'participant';
        }

        return null;
    }

    public function agendas()
    {
        return $this->hasMany(MeetingAgenda::class, 'meeting_id')->orderBy('sort_order');
    }

    public function documents()
    {
        return $this->hasMany(MeetingDocument::class, 'meeting_id')->orderBy('sort_order');
    }

    public function voteTopics()
    {
        return $this->hasMany(MeetingVoteTopic::class, 'meeting_id')->orderBy('sort_order');
    }

    public function currentAgenda()
    {
        return $this->belongsTo(MeetingAgenda::class, 'current_meeting_agenda_id');
    }

    public function currentDiscussionRegistration()
    {
        return $this->belongsTo(MeetingDiscussionRegistration::class, 'current_meeting_discussion_registration_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(isset($filters['is_public']), fn ($q) => $q->where('is_public', (bool) $filters['is_public']))
            ->when($filters['meeting_type_id'] ?? null, fn ($q, $meetingTypeId) => $q->where('meeting_type_id', $meetingTypeId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'title', 'start_time', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
