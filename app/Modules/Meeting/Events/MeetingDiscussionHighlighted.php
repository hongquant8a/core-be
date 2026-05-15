<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Operator highlight 1 đăng ký phát biểu/chất vấn lên màn chiếu.
 */
class MeetingDiscussionHighlighted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int|null  $autoCompletedPreviousId  Registration cũ vừa được auto-mark completed khi
     *                                              chuyển người phát biểu. Null nếu không có (lần
     *                                              đầu highlight, hoặc registration cũ đã completed).
     */
    public function __construct(
        public Meeting $meeting,
        public ?int $autoCompletedPreviousId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('meeting.'.$this->meeting->id)];
    }

    public function broadcastAs(): string
    {
        return 'meeting.discussion-highlighted';
    }

    public function broadcastWith(): array
    {
        $registration = null;
        if ($this->meeting->current_meeting_discussion_registration_id) {
            $registration = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
                ->with('participant')
                ->find($this->meeting->current_meeting_discussion_registration_id);
        }

        return [
            'meeting_id' => $this->meeting->id,
            'current_meeting_discussion_registration_id' => $this->meeting->current_meeting_discussion_registration_id,
            // Detail kèm để FE đại biểu match participant_id của mình → bắn modal
            // "Mời bạn lên trình bày". Null khi operator bỏ highlight.
            'registration' => $registration ? [
                'id' => $registration->id,
                'meeting_agenda_id' => $registration->meeting_agenda_id,
                'meeting_participant_id' => $registration->meeting_participant_id,
                'participant_name' => $registration->participant?->display_name,
                'type' => $registration->type,
                'content' => $registration->content,
                'status' => $registration->status,
            ] : null,
            // ID của registration cũ vừa được auto-completed khi chuyển người phát biểu.
            // FE dùng để update inline status row trong list mà không cần refetch.
            'auto_completed_previous_id' => $this->autoCompletedPreviousId,
        ];
    }
}
