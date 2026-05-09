<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingAttendee extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'position_name',
        'department_name',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

    /** Tự load user (+profile) khi serialize → name/email/phone accessor không bị N+1. */
    protected $with = ['user.profile'];

    protected $appends = ['name', 'email', 'phone'];

    protected static function booted()
    {
        static::creating(fn (MeetingAttendee $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingAttendee $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Many-to-many với MeetingAttendeeGroup qua pivot meeting_attendee_group_members.
     * Trước đây là belongsTo (FK đơn) — refactor 2026-05-08.
     */
    public function groups()
    {
        return $this->belongsToMany(
            MeetingAttendeeGroup::class,
            'meeting_attendee_group_members',
            'meeting_attendee_id',
            'meeting_attendee_group_id'
        )->withPivot('id', 'organization_id')->withTimestamps();
    }

    public function getNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->user?->profile?->phone;
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $orgId) => $q->where('organization_id', (int) $orgId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // Filter theo group qua pivot — chấp nhận `groups[]=1&groups[]=2` (multi)
            // hoặc `meeting_attendee_group_id=1` (legacy single — back-compat).
            ->when(! empty($filters['groups']) && is_array($filters['groups']), function ($q) use ($filters) {
                $q->whereHas('groups', fn ($g) => $g->whereIn('meeting_attendee_groups.id', $filters['groups']));
            })
            ->when($filters['meeting_attendee_group_id'] ?? null, function ($q, $groupId) {
                $q->whereHas('groups', fn ($g) => $g->where('meeting_attendee_groups.id', $groupId));
            })
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
