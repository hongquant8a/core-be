<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingAttendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator approve điểm danh (pending → present). FE Tab 6 + Tab 7 update.
 * FE dùng `is_attendance_confirmed` + `user_id` để update local state — khi khớp với user hiện tại
 * thì set is_attendance_confirmed=true, từ đó popup biểu quyết mới hiển thị khi nhận vote-topic.opened.
 */
class MeetingAttendanceApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingAttendance $attendance) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->attendance->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'attendance.approved';
    }

    public function broadcastWith(): array
    {
        $this->attendance->loadMissing('participant.attendee');

        return [
            'id' => $this->attendance->id,
            'meeting_id' => $this->attendance->meeting_id,
            'meeting_participant_id' => $this->attendance->meeting_participant_id,
            'user_id' => $this->attendance->participant?->attendee?->user_id,
            'status' => $this->attendance->status,
            'is_attendance_confirmed' => true,
        ];
    }
}
