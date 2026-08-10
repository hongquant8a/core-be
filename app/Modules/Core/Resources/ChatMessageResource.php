<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chat_conversation_id' => $this->chat_conversation_id,
            'sender_id' => $this->sender_user_id,
            'sender_name' => $this->sender?->name,
            'content' => $this->content,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
