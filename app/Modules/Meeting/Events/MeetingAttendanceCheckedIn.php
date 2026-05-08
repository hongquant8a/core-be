<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingAttendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu vừa submit điểm danh (status=pending hoặc đã có row trước đó được update).
 * Operator Tab 7 nghe event này → list "đang chờ duyệt" tự update không phải poll.
 */
class MeetingAttendanceCheckedIn implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingAttendance $attendance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->attendance->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'attendance.checked-in';
    }

    public function broadcastWith(): array
    {
        $this->attendance->loadMissing('participant');

        return [
            'id' => $this->attendance->id,
            'meeting_id' => $this->attendance->meeting_id,
            'meeting_participant_id' => $this->attendance->meeting_participant_id,
            'participant_name' => $this->attendance->participant?->display_name,
            'status' => $this->attendance->status,
            'checked_in_at' => $this->attendance->checked_in_at?->toIso8601String(),
        ];
    }
}
