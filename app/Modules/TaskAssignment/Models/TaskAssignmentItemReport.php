<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaskAssignmentItemReport extends TenantModel implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\TaskAssignment\Models\TaskAssignmentItemReportFactory::new();
    }

    protected $table = 'task_assignment_item_reports';

    protected $fillable = [
        'task_assignment_item_id',
        'reporter_user_id',
        'completion_percent',
        'completed_at',
        'report_document_number',
        'report_document_excerpt',
        'report_document_content',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'completion_percent' => 'integer',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (TaskAssignmentItemReport $model) {
            if (! $model->reporter_user_id) {
                $model->reporter_user_id = auth()->id();
            }
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function (TaskAssignmentItemReport $model) {
            $model->updated_by = auth()->id();
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
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
