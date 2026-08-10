<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting' => $this->meeting ? [
                'id' => $this->meeting->id,
                'title' => $this->meeting->title,
            ] : null,
            'messages_count' => $this->when(isset($this->messages_count), (int) $this->messages_count),
            'last_message_at' => $this->messages_max_created_at
                ? \Illuminate\Support\Carbon::parse($this->messages_max_created_at)->format('H:i:s d/m/Y')
                : null,
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
        ];
    }
}
