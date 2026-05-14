<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Template biên bản họp (.docx upload bởi admin). Service phpword TemplateProcessor
 * replace ${variable} bằng data thật của meeting khi user "Xuất biên bản".
 */
class MeetingMinutesTemplate extends TenantModel implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'media_id',
        'is_default',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (MeetingMinutesTemplate $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingMinutesTemplate $model) => $model->updated_by = auth()->id());
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('meeting-minutes-template-file');
    }

    public function scopeFilter($query, array $filters)
    {
        // Scope organization auto qua TenantModel/HasOrganizationScope global scope.
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['sort_by'] ?? 'updated_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'is_default', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'updated_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
