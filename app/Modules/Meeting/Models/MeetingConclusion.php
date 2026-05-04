<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MeetingConclusion extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'title',
        'content',
        'media_id',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingConclusion $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingConclusion $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('meeting-conclusion-attachments');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'title', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
