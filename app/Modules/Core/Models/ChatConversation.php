<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\ChatConversationTypeEnum;
use App\Modules\Meeting\Models\Meeting;

class ChatConversation extends TenantModel
{
    protected $fillable = [
        'organization_id',
        'type',
        'meeting_id',
        'user_one_id',
        'user_two_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(fn (ChatConversation $conversation) => $conversation->created_by = auth()->id());
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id')->orderBy('created_at');
    }

    public function scopeDirect($query)
    {
        return $query->where('type', ChatConversationTypeEnum::Direct->value);
    }

    public function scopeMeetingGroup($query)
    {
        return $query->where('type', ChatConversationTypeEnum::MeetingGroup->value);
    }
}
