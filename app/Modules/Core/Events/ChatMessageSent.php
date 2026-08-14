<?php

namespace App\Modules\Core\Events;

use App\Modules\Core\Enums\ChatConversationTypeEnum;
use App\Modules\Core\Models\ChatConversation;
use App\Modules\Core\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tin nhắn chat mới (nhóm cuộc họp hoặc riêng 1-1). Group → tái dùng channel `meeting.{id}`
 * đã có sẵn (participant đã được authorize qua routes/channels.php). Direct → 2 channel cá
 * nhân `org.{organizationId}.user.{userId}` của cả 2 người trong cuộc trò chuyện.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message, public ChatConversation $conversation) {}

    public function broadcastOn(): array
    {
        if ($this->conversation->type === ChatConversationTypeEnum::MeetingGroup->value) {
            return [new PrivateChannel('meeting.'.$this->conversation->meeting_id)];
        }

        return [
            new PrivateChannel('org.'.$this->conversation->organization_id.'.user.'.$this->conversation->user_one_id),
            new PrivateChannel('org.'.$this->conversation->organization_id.'.user.'.$this->conversation->user_two_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat-message.created';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'id' => $this->message->id,
            'chat_conversation_id' => $this->message->chat_conversation_id,
            'type' => $this->conversation->type,
            'meeting_id' => $this->conversation->meeting_id,
            'sender_id' => $this->message->sender_user_id,
            'sender_name' => $this->message->sender?->name,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
