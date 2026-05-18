<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingDiscussionRegistrationAttachment extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_discussion_registration_id',
        'media_id',
        'file_name',
        'sort_order',
    ];

    public function registration()
    {
        return $this->belongsTo(MeetingDiscussionRegistration::class, 'meeting_discussion_registration_id');
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }
}
