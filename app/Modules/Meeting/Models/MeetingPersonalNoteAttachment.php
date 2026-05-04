<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingPersonalNoteAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_personal_note_id',
        'media_id',
        'sort_order',
    ];

    public function note()
    {
        return $this->belongsTo(MeetingPersonalNote::class, 'meeting_personal_note_id');
    }

    public function mediaFile()
    {
        return $this->belongsTo(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'media_id');
    }
}
