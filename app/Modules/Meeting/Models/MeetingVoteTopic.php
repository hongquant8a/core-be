<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingVoteTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_agenda_id',
        'title',
        'vote_type',
        'ballot_mode',
        'show_result_on_projector',
        'show_result_on_personal_device',
        'sort_order',
        'status',
        'opened_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'show_result_on_projector' => 'boolean',
        'show_result_on_personal_device' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingVoteTopic $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingVoteTopic $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }
}
