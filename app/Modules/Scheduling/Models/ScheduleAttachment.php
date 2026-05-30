<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ScheduleAttachment extends TenantModel
{
    use HasFactory;

    protected $table = 'schedule_attachments';

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'media_id',
        'file_name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'media_id' => 'integer',
        'schedule_id' => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function mediaFile()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
