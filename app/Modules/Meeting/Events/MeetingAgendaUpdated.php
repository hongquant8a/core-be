<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Resources\MeetingAgendaResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Update chương trình họp (có thể update lời dẫn, nội dung...).
 * Broadcast ngay lập tức (ShouldBroadcastNow) để FE update state realtime.
 */
class MeetingAgendaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingAgenda $meetingAgenda) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meetingAgenda->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.agenda-updated';
    }

    public function broadcastWith(): array
    {
        $this->meetingAgenda->loadMissing(['parent', 'children']);

        return (new MeetingAgendaResource($this->meetingAgenda))
            ->toArray(request() ?? new \Illuminate\Http\Request);
    }
}
