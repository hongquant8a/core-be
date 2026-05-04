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
        'meeting_attendee_group_id',
        'user_id',
        'name',
        'position_name',
        'department_name',
        'email',
        'phone',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

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

    public function group()
    {
        return $this->belongsTo(MeetingAttendeeGroup::class, 'meeting_attendee_group_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $orgId) => $q->where('organization_id', (int) $orgId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['meeting_attendee_group_id'] ?? null, fn ($q, $groupId) => $q->where('meeting_attendee_group_id', $groupId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
