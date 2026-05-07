<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingAttendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator reject điểm danh (pending → absent).
 */
class MeetingAttendanceRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingAttendance $attendance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->attendance->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'attendance.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->attendance->id,
            'meeting_id' => $this->attendance->meeting_id,
            'meeting_participant_id' => $this->attendance->meeting_participant_id,
            'status' => $this->attendance->status,
        ];
    }
}
