<?php

namespace App\Modules\Meeting\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu rút đăng ký phát biểu/chất vấn (DELETE row). Tab 5 + Tab 7 list xoá item.
 *
 * Truyền raw id thay vì model vì model đã bị xoá tại thời điểm broadcast.
 */
class MeetingDiscussionRegistrationDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $registrationId, public int $meetingId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meetingId)];
    }

    public function broadcastAs(): string
    {
        return 'discussion-registration.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->registrationId,
            'meeting_id' => $this->meetingId,
        ];
    }
}
