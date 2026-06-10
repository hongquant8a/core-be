<?php

namespace App\Modules\TaskAssignment\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskAssignmentPetitionAttachment extends Model
{
    protected $table = 'task_assignment_petition_attachments';

    protected $fillable = [
        'petition_id',
        'media_id',
        'file_name',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function petition()
    {
        return $this->belongsTo(TaskAssignmentPetition::class, 'petition_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
