<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_type_id',
        'meeting_location_id',
        'title',
        'is_public',
        'content',
        'start_time',
        'end_time',
        'status',
        'view_count',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'meeting_type_id' => 'integer',
        'meeting_location_id' => 'integer',
        'is_public' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'published_at' => 'datetime',
        'view_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (Meeting $meeting) {
            $meeting->created_by = auth()->id();
            $meeting->updated_by = auth()->id();
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

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(isset($filters['is_public']), fn ($q) => $q->where('is_public', (bool) $filters['is_public']))
            ->when($filters['meeting_type_id'] ?? null, fn ($q, $meetingTypeId) => $q->where('meeting_type_id', $meetingTypeId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'title', 'start_time', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
