<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

class MeetingView extends Model
{
    protected $table = 'meeting_views';

    public $timestamps = false;

    protected $fillable = [
        'meeting_id',
        'meeting_document_id',
        'user_id',
        'ip_address',
        'user_agent',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function meetingDocument()
    {
        return $this->belongsTo(MeetingDocument::class, 'meeting_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
