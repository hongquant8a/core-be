<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu vừa phản hồi mời họp (accepted / declined).
 * FE tab "Xác nhận tham gia" nghe event này → cập nhật list + stats realtime.
 */
class MeetingParticipantResponded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingParticipant $participant) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->participant->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'participant.responded';
    }

    public function broadcastWith(): array
    {
        $this->participant->loadMissing('attendee.user');

        return [
            'id' => $this->participant->id,
            'meeting_id' => $this->participant->meeting_id,
            'meeting_attendee_id' => $this->participant->meeting_attendee_id,
            'user_id' => $this->participant->attendee?->user_id,
            'display_name' => $this->participant->display_name,
            'response_status' => $this->participant->response_status,
            'absence_reason' => $this->participant->absence_reason,
            'responded_at' => $this->participant->responded_at?->toIso8601String(),
        ];
    }
}
