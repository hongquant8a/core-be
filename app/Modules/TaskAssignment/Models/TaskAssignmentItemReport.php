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
    ];

    protected $casts = [
        'completed_at' => 'datetime',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('task-report-attachments');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAssignmentItemReportAttachment::class, 'task_assignment_item_report_id');
    }
}
