<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu vừa đăng ký phát biểu / chất vấn. Chair + operator nghe để cập nhật
 * danh sách đăng ký realtime, không phải poll endpoint stats nữa.
 */
class MeetingDiscussionRegistrationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingDiscussionRegistration $registration) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->registration->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'discussion-registration.created';
    }

    public function broadcastWith(): array
    {
        $this->registration->loadMissing('participant');

        return [
            'id' => $this->registration->id,
            'meeting_id' => $this->registration->meeting_id,
            'meeting_agenda_id' => $this->registration->meeting_agenda_id,
            'meeting_participant_id' => $this->registration->meeting_participant_id,
            'participant_name' => $this->registration->participant?->display_name,
            'type' => $this->registration->type,
            'content' => $this->registration->content,
            'media_id' => $this->registration->media_id,
            'status' => $this->registration->status,
            'sort_order' => $this->registration->sort_order,
        ];
    }
}
