<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskAssignmentItem extends Model implements HasMedia
{
    use HasFactory, HasOrganizationScope;
    use InteractsWithMedia;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentItemFactory::new();
    }

    protected $table = 'task_assignment_items';

    protected $fillable = [
        'task_assignment_document_id',
        'name',
        'description',
        'task_assignment_item_type_id',
        'deadline_type',
        'start_at',
        'end_at',
        'processing_status',
        'completion_percent',
        'priority',
        'completed_at',
        'assigned_by',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_at'           => 'datetime',
        'end_at'             => 'datetime',
        'completed_at'       => 'datetime',
        'completion_percent' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn (TaskAssignmentItem $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (TaskAssignmentItem $model) => $model->updated_by = auth()->id());
    }

    /**
     * "Trễ hạn" — task chưa hoàn thành mà đã quá `end_at`.
     * Derive từ dates, không lưu DB.
     *
     * Timing cho báo cáo đã hoàn thành (on_time / late) nằm ở ReportResource.timing_status,
     * không phải thuộc tính của task.
     */
    public function isOverdue(): bool
    {
        return $this->deadline_type === \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value
            && $this->end_at
            && $this->end_at->isPast()
            && ! in_array($this->processing_status, [
                \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
            ], true);
    }

    public function document()
    {
        return $this->belongsTo(TaskAssignmentDocument::class, 'task_assignment_document_id');
    }

    public function itemType()
    {
        return $this->belongsTo(TaskAssignmentItemType::class, 'task_assignment_item_type_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'task_assignment_item_user', 'task_assignment_item_id', 'user_id')
            ->withPivot('department_id', 'department_role', 'assignment_role', 'assignment_status', 'assigned_at', 'accepted_at', 'completed_at', 'note')
            ->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(TaskAssignmentDepartment::class, 'task_assignment_item_user', 'task_assignment_item_id', 'department_id')
            ->withPivot('department_role')
            ->distinct();
    }

    public function reports()
    {
        return $this->hasMany(TaskAssignmentItemReport::class, 'task_assignment_item_id');
    }

    public function transfers()
    {
        return $this->hasMany(TaskAssignmentItemUserTransfer::class, 'task_assignment_item_id');
    }

    public function notes()
    {
        return $this->hasMany(TaskAssignmentItemNote::class, 'task_assignment_item_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAssignmentItemAttachment::class, 'task_assignment_item_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('task-item-attachments');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['processing_status'] ?? null, function ($q, $status) {
                // Giá trị 'overdue' không còn trong enum — map thành predicate is_overdue để đồng bộ với stats counter.
                if ($status === 'overdue') {
                    $q->where('deadline_type', \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value)
                        ->where('end_at', '<', now())
                        ->whereNotIn('processing_status', [
                            \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                            \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
                        ]);

                    return;
                }
                $q->where('processing_status', $status);
            })
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['deadline_type'] ?? null, fn ($q, $type) => $q->where('deadline_type', $type))
            ->when($filters['task_assignment_document_id'] ?? null, fn ($q, $docId) => $q->where('task_assignment_document_id', $docId))
            ->when($filters['task_assignment_item_type_id'] ?? null, fn ($q, $typeId) => $q->where('task_assignment_item_type_id', $typeId))
            ->when($filters['department_id'] ?? null, fn ($q, $deptId) => $q->whereHas('users', fn ($q2) => $q2
                ->where('task_assignment_item_user.department_id', $deptId)
                ->where('task_assignment_item_user.assignment_status', '!=', \App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum::Transferred->value)
            ))
            ->when($filters['user_id'] ?? $filters['assignee_id'] ?? null, fn ($q, $userId) => $q->whereHas('users', fn ($q2) => $q2
                ->where('users.id', $userId)
                ->where('task_assignment_item_user.assignment_status', '!=', \App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum::Transferred->value)
            ))
            ->when($filters['assignment_role'] ?? null, fn ($q, $role) => $q->whereHas('users', fn ($q2) => $q2
                ->where('task_assignment_item_user.assignment_role', $role)
                ->where('task_assignment_item_user.assignment_status', '!=', \App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum::Transferred->value)
            ))
            ->when($filters['assignment_status'] ?? null, fn ($q, $status) => $q->whereHas('users', fn ($q2) => $q2->where('task_assignment_item_user.assignment_status', $status)))
            ->when($filters['assigned_by_or_created_by'] ?? $filters['assigner_id'] ?? null, fn ($q, $userId) => $q->where(fn ($q2) => $q2->where('assigned_by', $userId)->orWhere('created_by', $userId)))
            ->when($filters['start_from'] ?? null, fn ($q, $date) => $q->whereDate('start_at', '>=', $date))
            ->when($filters['start_to'] ?? null, fn ($q, $date) => $q->whereDate('start_at', '<=', $date))
            ->when($filters['end_from'] ?? null, fn ($q, $date) => $q->whereDate('end_at', '>=', $date))
            ->when($filters['end_to'] ?? null, fn ($q, $date) => $q->whereDate('end_at', '<=', $date))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            // Computed flag is_overdue: deadline_type=has_deadline AND end_at<now AND status NOT IN (done, cancelled).
            // "Đang trễ hạn" — task chưa hoàn thành mà quá `end_at`.
            ->when(isset($filters['is_overdue']) && filter_var($filters['is_overdue'], FILTER_VALIDATE_BOOLEAN), fn ($q) => $q
                ->where('deadline_type', \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value)
                ->where('end_at', '<', now())
                ->whereNotIn('processing_status', [
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
                ]))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'start_at', 'end_at', 'completion_percent', 'priority', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                $q->orderBy($column, $filters['sort_order'] ?? 'desc');
            });
    }
}
