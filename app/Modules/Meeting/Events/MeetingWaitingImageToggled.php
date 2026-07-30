<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event bắn tín hiệu realtime để màn chiếu (Tab 8) bật/tắt ảnh chờ chương trình.
 * Broadcast ngay lập tức (ShouldBroadcastNow) — không qua queue.
 */
class MeetingWaitingImageToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public bool $isActive,
        public ?string $waitingImageUrl
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meeting->id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.waiting-image-toggled';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id'        => $this->meeting->id,
            'is_active'         => $this->isActive,
            'waiting_image_url' => $this->waitingImageUrl,
        ];
    }
}
