<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator highlight 1 chương trình lên màn chiếu. Tab 8 + Tab 7 đồng bộ slide.
 * Payload chỉ truyền agenda_id (null = bỏ highlight) — FE đã có agenda data trong store.
 */
class MeetingAgendaHighlighted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meeting->id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.agenda-highlighted';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'current_meeting_agenda_id' => $this->meeting->current_meeting_agenda_id,
        ];
    }
}
