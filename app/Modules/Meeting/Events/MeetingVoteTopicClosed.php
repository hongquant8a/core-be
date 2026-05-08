<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator đóng phiên biểu quyết. FE đại biểu nhận → đóng popup, hiển thị
 * kết quả nếu show_result_on_personal_device. Tab 8 màn chiếu chuyển slide.
 */
class MeetingVoteTopicClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingVoteTopic $topic) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->topic->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'vote-topic.closed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->topic->id,
            'meeting_id' => $this->topic->meeting_id,
            'show_result_on_projector' => (bool) $this->topic->show_result_on_projector,
            'show_result_on_personal_device' => (bool) $this->topic->show_result_on_personal_device,
            'phase' => 'closed',
            'closed_at' => $this->topic->closed_at?->toIso8601String(),
        ];
    }
}
