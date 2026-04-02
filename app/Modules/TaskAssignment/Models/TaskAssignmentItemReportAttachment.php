<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskAssignmentItemReportAttachment extends Model
{
    protected $table = 'task_assignment_item_report_attachments';

    protected $fillable = [
        'task_assignment_item_report_id',
        'media_id',
        'file_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function report()
    {
        return $this->belongsTo(TaskAssignmentItemReport::class, 'task_assignment_item_report_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
