<?php

namespace App\Modules\Meeting\Events;

use App\Modules\Meeting\Models\MeetingVoteResponse;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Đại biểu vừa bỏ phiếu. FE màn chiếu / Tab 7 nhận event để update count realtime.
 *
 * Payload tối giản theo ballot_mode để không leak danh tính:
 *  - anonymous: không gửi participant_id/name. FE dùng count trên `total` từ stats endpoint
 *    (hoặc tự +1 vào counter hiện tại).
 *  - public_named: gửi participant_id + name. FE delegate vẫn không hiển thị (theo policy
 *    UI), chỉ chair/op render. BE-side privilege check là tại REST endpoint, channel này
 *    broadcast cho mọi subscriber → FE phải tự gate display, hoặc tách channel theo role
 *    (defer phase sau).
 *
 * Để nhất quán với spec line 166 + commit A REST gate: payload luôn anonymized
 * (không truyền participant info qua channel chung). Caller cần detail → fetch REST.
 */
class MeetingVoteResponseAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingVoteResponse $response) {}

    public function broadcastOn(): array
    {
        $this->response->loadMissing('topic');

        return [new PrivateChannel('meeting.'.$this->response->topic->meeting_id)];
    }

    public function broadcastAs(): string
    {
        return 'vote-response.added';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_vote_topic_id' => $this->response->meeting_vote_topic_id,
            'option' => $this->response->option,
            'voted_at' => $this->response->voted_at?->toIso8601String(),
        ];
    }
}
