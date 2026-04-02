<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskAssignmentDocumentAttachment extends Model
{
    protected $table = 'task_assignment_document_attachments';

    protected $fillable = [
        'task_assignment_document_id',
        'media_id',
        'file_name',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function document()
    {
        return $this->belongsTo(TaskAssignmentDocument::class, 'task_assignment_document_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
