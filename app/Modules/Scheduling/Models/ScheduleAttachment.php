<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\User;

class ScheduleAttachment extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_attachments';

    protected $fillable = [
        'schedule_id', 'title', 'file_name', 'file_path', 'file_size', 'mime_type', 'sort_order', 'uploaded_by', 'created_at',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
