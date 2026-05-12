<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDiscussionRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_agenda_id' => $this->meeting_agenda_id,
            'meeting_participant_id' => $this->meeting_participant_id,
            'participant_name' => $this->participant?->display_name,
            'type' => $this->type,
            'content' => $this->content,
            // Operator/Chair fill sau khi đại biểu xong lượt. type=discussion -> ghi chú thảo luận;
            // type=question -> nội dung trả lời chất vấn.
            'operator_note' => $this->operator_note,
            'media_id' => $this->media_id,
            'file_url' => $this->mediaFile ? '/storage/'.$this->mediaFile->id.'/'.$this->mediaFile->file_name : null,
            'file_name' => $this->mediaFile?->file_name,
            'status' => $this->status,
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            // ISO timestamp (UTC) — FE compute countdown: agenda.duration_minutes - (now - highlighted_at).
            'highlighted_at' => $this->highlighted_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
