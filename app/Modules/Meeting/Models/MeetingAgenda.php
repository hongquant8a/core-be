<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingAgenda extends TenantModel
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
        'discussion_duration_minutes',
        'allow_question_registration',
        'question_duration_minutes',
        'parent_id',
        'sort_order',
    ];

    protected $casts = [
        'allow_discussion_registration' => 'boolean',
        'allow_question_registration' => 'boolean',
        'discussion_duration_minutes' => 'integer',
        'question_duration_minutes' => 'integer',
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
        $query->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('content', 'like', '%'.$search.'%'))
            ->when($filters['parent_id'] ?? null, fn ($q, $parentId) => $q->where('parent_id', $parentId))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'sort_order', 'start_time', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'asc');
            });
    }
}
