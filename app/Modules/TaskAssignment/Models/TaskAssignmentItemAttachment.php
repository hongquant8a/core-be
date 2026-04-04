<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskAssignmentItemAttachment extends Model
{
    protected $table = 'task_assignment_item_attachments';

    protected $fillable = [
        'task_assignment_item_id',
        'media_id',
        'file_name',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
