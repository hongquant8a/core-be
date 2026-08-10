<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $counterpart = $currentUserId && (int) $this->user_one_id === (int) $currentUserId
            ? $this->userTwo
            : $this->userOne;

        $lastMessage = $this->messages->first();

        return [
            'id' => $this->id,
            'counterpart' => $counterpart ? [
                'id' => $counterpart->id,
                'name' => $counterpart->name,
            ] : null,
            'last_message' => $lastMessage ? [
                'content' => $lastMessage->content,
                'sender_id' => $lastMessage->sender_user_id,
                'created_at' => $lastMessage->created_at?->format('H:i:s d/m/Y'),
            ] : null,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
