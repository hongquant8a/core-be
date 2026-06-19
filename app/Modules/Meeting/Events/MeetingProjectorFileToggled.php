<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event bắn tín hiệu để realtime màn chiếu hiển thị (hoặc tắt) một file tài liệu
 * khi người điều hành mở/đóng file đó.
 */
class MeetingProjectorFileToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public ?string $fileUrl,
        public ?string $fileName,
        public ?string $fileType,
        public bool $isOpen
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meeting->id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.projector-file-toggled';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'file_url' => $this->fileUrl,
            'file_name' => $this->fileName,
            'file_type' => $this->fileType,
            'is_open' => $this->isOpen,
        ];
    }
}
