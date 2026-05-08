<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator đánh dấu xong 1 đăng ký phát biểu/chất vấn (registered → completed).
 * Chair Tab 6 + Tab 5 list cập nhật state realtime.
 */
class MeetingDiscussionRegistrationCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingDiscussionRegistration $registration) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->registration->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'discussion-registration.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->registration->id,
            'meeting_id' => $this->registration->meeting_id,
            'type' => $this->registration->type,
            'status' => $this->registration->status,
            'completed_at' => $this->registration->completed_at?->toIso8601String(),
        ];
    }
}
