<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskAssignmentDocument extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentDocumentFactory::new();
    }

    protected $table = 'task_assignment_documents';

    protected $fillable = [
        'name',
        'summary',
        'issue_date',
        'task_assignment_type_id',
        'status',
        'issued_at',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'issued_at'  => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(fn (TaskAssignmentDocument $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (TaskAssignmentDocument $model) => $model->updated_by = auth()->id());
    }

    public function type()
    {
        return $this->belongsTo(TaskAssignmentType::class, 'task_assignment_type_id');
    }

    public function items()
    {
        return $this->hasMany(TaskAssignmentItem::class, 'task_assignment_document_id');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAssignmentDocumentAttachment::class, 'task_assignment_document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('task-document-attachments');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['task_assignment_type_id'] ?? null, fn ($q, $typeId) => $q->where('task_assignment_type_id', $typeId))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '<=', $date))
            ->when($filters['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'issue_date', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
