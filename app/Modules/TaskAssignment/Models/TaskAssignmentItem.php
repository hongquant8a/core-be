<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Services\Notification\Contracts\Remindable;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class TaskAssignmentItem extends TenantModel implements HasMedia, Remindable
{
    use HasFactory;
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
        'rejection_reason',
        'reported_at',
        'reported_by',
        'priority',
        'completed_at',
        'approved_by',
        'assigned_by',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_at'           => 'datetime',
        'end_at'             => 'datetime',
        'reported_at'        => 'datetime',
        'completed_at'       => 'datetime',
        'completion_percent' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn (TaskAssignmentItem $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (TaskAssignmentItem $model) => $model->updated_by = auth()->id());

        // Global scope: mặc định chỉ hiển thị item thuộc văn bản đã ban hành.
        static::addGlobalScope('issuedDocument', function ($query) {
            $query->whereHas('document', fn ($dq) => $dq->where('status', \App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum::Issued->value));
        });
    }

    /**
     * Khi resolve model từ route (show/update/destroy/...), bỏ global scope
     * để truy cập được item thuộc văn bản chưa ban hành (draft).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('issuedDocument')->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
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
            && $this->end_at->endOfDay()->isPast()
            && ! in_array($this->processing_status, [
                \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
            ], true);
    }

    /**
     * "Hoàn thành trễ hạn" — task đã hoàn thành nhưng completed_at > end_at.
     */
    public function isLate(): bool
    {
        return $this->processing_status === \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value
            && $this->deadline_type === \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value
            && $this->completed_at
            && $this->end_at
            && $this->completed_at->toDateString() > $this->end_at->toDateString();
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

    // -- Bắt đầu implement Remindable --

    public function getReminderDeadline(): ?Carbon
    {
        return $this->end_at;
    }

    public function getReminderOrganizationId(): int
    {
        return (int) ($this->document?->organization_id ?? 0);
    }

    public function getReminderModuleKey(): string
    {
        return NotificationModuleEnum::TaskAssignment->value; // 'task_assignment'
    }

    public function getReminderEventKeys(): array
    {
        return [
            NotificationEventEnum::ReminderBefore->value, // 'reminder_before'
            NotificationEventEnum::ReminderOn->value,     // 'reminder_on'
            NotificationEventEnum::ReminderAfter->value,  // 'reminder_after'
        ];
    }

    public function getReminderEventKey(?string $moment): string
    {
        return match ($moment) {
            null    => NotificationEventEnum::DocumentIssued->value,
            'before'=> NotificationEventEnum::ReminderBefore->value,
            'on'    => NotificationEventEnum::ReminderOn->value,
            'after' => NotificationEventEnum::ReminderAfter->value,
            default => "reminder_{$moment}",
        };
    }

    public function resolveReminderRecipients(): Collection
    {
        $this->loadMissing('users');
        return $this->users;
    }

    public function resolveGuestReminderRecipients(): Collection
    {
        return collect();
    }

    public function isValidForReminder(): bool
    {
        $this->loadMissing('document');

        return !in_array($this->processing_status, ['done', 'cancelled'], true)
            && ($this->document?->status ?? null) === TaskAssignmentDocumentStatusEnum::Issued->value;
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // -- Kết thúc implement Remindable --

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

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        // Khi lọc theo văn bản cụ thể, bỏ global scope để hiển thị cả bản nháp.
        if (! empty($filters['task_assignment_document_id'])) {
            $query->withoutGlobalScope('issuedDocument');
        }

        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['processing_status'] ?? null, function ($q, $status) {
                $q->where('processing_status', $status);
            })
            ->when($filters['timing_status'] ?? null, function ($q, $timing) {
                $done = \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value;
                $cancelled = \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value;
                $hasDeadline = \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value;

                if ($timing === 'upcoming') {
                    $q->whereNotIn('processing_status', [$done, $cancelled])
                        ->where(fn ($sub) => $sub->where('deadline_type', '!=', $hasDeadline)
                            ->orWhereNull('end_at')
                            ->orWhere('end_at', '>=', now()->startOfDay())
                        );
                } elseif ($timing === 'overdue') {
                    $q->whereNotIn('processing_status', [$done, $cancelled])
                        ->where('deadline_type', $hasDeadline)
                        ->where('end_at', '<', now()->startOfDay());
                } elseif ($timing === 'late') {
                    $q->where('processing_status', $done)
                        ->where('deadline_type', $hasDeadline)
                        ->whereRaw('DATE(completed_at) > DATE(end_at)');
                } elseif ($timing === 'early') {
                    $q->where('processing_status', $done)
                        ->where('deadline_type', $hasDeadline)
                        ->whereRaw('DATE(completed_at) < DATE(end_at)');
                } elseif ($timing === 'on_time') {
                    $q->where('processing_status', $done)
                        ->where(fn ($sub) => $sub->where('deadline_type', '!=', $hasDeadline)
                            ->orWhereNull('end_at')
                            ->orWhereRaw('DATE(completed_at) = DATE(end_at)')
                        );
                } elseif ($timing === 'cancelled') {
                    $q->where('processing_status', $cancelled);
                }
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
                ->where('end_at', '<', now()->startOfDay())
                ->whereNotIn('processing_status', [
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
                ]))
            // Sort: FE truyền sort_by → tôn trọng. Mặc định → composite "smart sort" theo spec:
            //   1. Ưu tiên (priority) DESC: urgent → high → medium → low
            //   2. Quá hạn DESC: task overdue (has_deadline + end_at < now + chưa done/cancelled) trước
            //   3. Gần đến hạn ASC: end_at sớm trước (NULL/no deadline đẩy về cuối)
            ->when(empty($filters['sort_by']), function ($q) {
                $doneStatuses = [
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value,
                    \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value,
                ];
                $doneIn = "'".implode("','", $doneStatuses)."'";
                $hasDeadline = \App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum::HasDeadline->value;

                $q->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
                    ->orderByRaw("CASE WHEN deadline_type = '$hasDeadline' AND DATE(end_at) < DATE(NOW()) AND processing_status NOT IN ($doneIn) THEN 1 ELSE 0 END DESC")
                    ->orderByRaw('end_at IS NULL ASC')
                    ->orderBy('end_at', 'asc');
            }, function ($q) use ($filters) {
                $sortBy = $filters['sort_by'];
                $allowed = ['id', 'name', 'start_at', 'end_at', 'completion_percent', 'priority', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
