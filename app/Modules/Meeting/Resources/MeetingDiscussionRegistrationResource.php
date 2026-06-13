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
            'participant_name'             => $this->participant?->display_name,
            'is_attendance_confirmed'       => $this->participant?->attendance?->status === 'present',
            'type' => $this->type,
            'content' => $this->content,
            // Operator/Chair fill sau khi đại biểu xong lượt.
            //   - operator_note    : ghi chú thảo luận (áp dụng type=discussion)
            //   - answer_content   : nội dung trả lời chất vấn (áp dụng type=question)
            'operator_note' => $this->operator_note,
            'answer_content' => $this->answer_content,
            'answer_attachment_id' => $this->answer_attachment_id,
            'answer_attachment_url' => $this->answerAttachment ? '/storage/'.$this->answerAttachment->id.'/'.$this->answerAttachment->file_name : null,
            'answer_attachment_name' => $this->answerAttachment?->file_name,
            // Legacy single attachment (media_id) — giữ cho backward-compat, FE mới dùng
            // mảng attachments bên dưới (multi-file).
            'media_id' => $this->media_id,
            'file_url' => $this->mediaFile ? '/storage/'.$this->mediaFile->id.'/'.$this->mediaFile->file_name : null,
            'file_name' => $this->mediaFile?->file_name,
            // Multi-attachment — list file đính kèm, có thể nhiều, user đặt được file_name hiển thị.
            'attachments' => MeetingDiscussionRegistrationAttachmentResource::collection(
                $this->whenLoaded('attachments')
            ),
            // Count gọn cho FE hiển thị icon đính kèm + badge số file (Tab thảo luận/chất vấn,
            // tab slide). Tính qua relation nếu đã eager-load, fallback withCount nếu cần.
            'attachments_count' => $this->whenLoaded('attachments', fn () => $this->attachments->count())
                ?? ($this->attachments_count ?? 0),
            'status' => $this->status,
            'is_public' => $this->is_public,
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            // ISO timestamp (UTC) — FE compute countdown: agenda.duration_minutes - (now - highlighted_at).
            'highlighted_at' => $this->highlighted_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
