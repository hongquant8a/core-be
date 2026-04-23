<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskAssignmentItemReport extends Model implements HasMedia
{
    use HasFactory, HasOrganizationScope;
    use InteractsWithMedia;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentItemReportFactory::new();
    }

    protected $table = 'task_assignment_item_reports';

    protected $fillable = [
        'task_assignment_item_id',
        'reporter_user_id',
        'completed_at',
        'report_document_number',
        'report_document_excerpt',
        'report_document_content',
        'organization_id',
        'manager_confirmed',
        'manager_confirmed_by',
        'manager_confirmed_at',
        'manager_confirm_note',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'manager_confirmed' => 'boolean',
        'manager_confirmed_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (TaskAssignmentItemReport $model) {
            if (! $model->reporter_user_id) {
                $model->reporter_user_id = auth()->id();
            }
        });
    }

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function managerConfirmer()
    {
        return $this->belongsTo(User::class, 'manager_confirmed_by');
    }

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Timing của báo cáo so với deadline task:
     * - 'on_time': completed_at ≤ task.end_at (đúng hạn)
     * - 'late':    completed_at > task.end_at (trễ hạn)
     * - null:      thiếu completed_at hoặc task no_deadline
     *
     * Derive on-the-fly, không lưu DB.
     */
    public function timingStatus(): ?string
    {
        if (! $this->completed_at) {
            return null;
        }

        $deadline = $this->item?->end_at;
        if (! $deadline) {
            return null;
        }

        return $this->completed_at->lte($deadline) ? 'on_time' : 'late';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('task-report-attachments');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAssignmentItemReportAttachment::class, 'task_assignment_item_report_id');
    }
}
