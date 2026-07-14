<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingAttendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator hủy điểm danh (xóa bản ghi điểm danh).
 */
class MeetingAttendanceCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingAttendance $attendance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->attendance->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'attendance.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->attendance->id,
            'meeting_id' => $this->attendance->meeting_id,
            'meeting_participant_id' => $this->attendance->meeting_participant_id,
            'status' => 'cancelled',
        ];
    }
}
