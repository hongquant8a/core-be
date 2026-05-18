<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu sửa nội dung đăng ký phát biểu/chất vấn (trước khi được gọi lên).
 * Tab 5 + Tab 7 list cập nhật content/sort_order/status realtime.
 */
class MeetingDiscussionRegistrationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingDiscussionRegistration $registration) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->registration->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'discussion-registration.updated';
    }

    public function broadcastWith(): array
    {
        // Load đầy đủ relations để payload WS = shape Resource cho FE upsert-by-id.
        // Bao gồm attachments (multi-file) — quan trọng cho FE Tab 7 hiển thị chip count.
        $this->registration->loadMissing(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile']);

        return (new \App\Modules\Meeting\Resources\MeetingDiscussionRegistrationResource($this->registration))
            ->toArray(request() ?? new \Illuminate\Http\Request);
    }
}
