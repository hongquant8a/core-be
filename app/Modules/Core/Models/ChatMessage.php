<?php

namespace App\Modules\Core\Models;

class ChatMessage extends TenantModel
{
    protected $fillable = [
        'organization_id',
        'chat_conversation_id',
        'sender_user_id',
        'content',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
