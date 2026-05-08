<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator mở phiên biểu quyết. FE app đại biểu nghe event này → bật popup vote.
 *
 * Payload đầy đủ topic info (description, duration_minutes, ballot_mode, vote_type)
 * vì cần ngay cho UI popup. Stats riêng phải fetch REST nếu muốn.
 */
class MeetingVoteTopicOpened implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingVoteTopic $topic) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->topic->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'vote-topic.opened';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->topic->id,
            'meeting_id' => $this->topic->meeting_id,
            'meeting_agenda_id' => $this->topic->meeting_agenda_id,
            'title' => $this->topic->title,
            'description' => $this->topic->description,
            'duration_minutes' => $this->topic->duration_minutes,
            'vote_type' => $this->topic->vote_type,
            'ballot_mode' => $this->topic->ballot_mode,
            'show_result_on_projector' => (bool) $this->topic->show_result_on_projector,
            'show_result_on_personal_device' => (bool) $this->topic->show_result_on_personal_device,
            'phase' => $this->topic->derivePhase(),
            'opened_at' => $this->topic->opened_at?->toIso8601String(),
            'expires_at_iso' => $this->topic->expiresAt()?->toIso8601String(),
        ];
    }
}
