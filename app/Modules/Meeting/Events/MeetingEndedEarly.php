<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator bấm "Kết thúc cuộc họp" — set end_time = now(). FE phase derive thành
 * `finished` ngay, không cần đợi đến giờ end_time dự kiến.
 */
class MeetingEndedEarly implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meeting->id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.ended-early';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'end_time' => $this->meeting->end_time?->toIso8601String(),
        ];
    }
}
