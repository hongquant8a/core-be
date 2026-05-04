<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingAgenda extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'start_time',
        'end_time',
        'content',
        'person_in_charge',
        'allow_discussion_registration',
        'allow_question_registration',
        'parent_id',
        'sort_order',
    ];

    protected $casts = [
        'allow_discussion_registration' => 'boolean',
        'allow_question_registration' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->when($filters['parent_id'] ?? null, fn ($q, $parentId) => $q->where('parent_id', $parentId))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'sort_order', 'start_time', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }
}
