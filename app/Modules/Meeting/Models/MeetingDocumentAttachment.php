<?php

namespace App\Modules\Meeting\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MeetingDocumentAttachment extends Model
{
    protected $table = 'meeting_document_attachments';

    protected $fillable = [
        'organization_id',
        'meeting_document_id',
        'media_id',
        'file_name',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingDocumentAttachment $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingDocumentAttachment $model) => $model->updated_by = auth()->id());
    }

    public function document()
    {
        return $this->belongsTo(MeetingDocument::class, 'meeting_document_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
