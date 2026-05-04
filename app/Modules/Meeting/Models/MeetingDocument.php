<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MeetingDocument extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'meeting_agenda_id',
        'meeting_document_type_id',
        'title',
        'document_number',
        'summary',
        'media_id',
        'is_public',
        'status',
        'view_count',
        'download_count',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'view_count' => 'integer',
        'download_count' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingDocument $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingDocument $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function agenda()
    {
        return $this->belongsTo(MeetingAgenda::class, 'meeting_agenda_id');
    }

    public function documentType()
    {
        return $this->belongsTo(MeetingDocumentType::class, 'meeting_document_type_id');
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('meeting-document-attachments');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        $query->when($organizationId, fn ($q, $organizationId) => $q->where('organization_id', (int) $organizationId))
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['meeting_agenda_id'] ?? null, fn ($q, $agendaId) => $q->where('meeting_agenda_id', $agendaId))
            ->when($filters['meeting_document_type_id'] ?? null, fn ($q, $typeId) => $q->where('meeting_document_type_id', $typeId))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', '%'.$search.'%')
                        ->orWhere('document_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(isset($filters['is_public']), fn ($q) => $q->where('is_public', (bool) $filters['is_public']))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }
}
