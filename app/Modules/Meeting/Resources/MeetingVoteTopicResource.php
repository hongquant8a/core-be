<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingVoteTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_agenda_id' => $this->meeting_agenda_id,
            'title' => $this->title,
            'vote_type' => $this->vote_type,
            'ballot_mode' => $this->ballot_mode,
            'show_result_on_projector' => $this->show_result_on_projector,
            'show_result_on_personal_device' => $this->show_result_on_personal_device,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->format('H:i:s d/m/Y'),
            'closed_at' => $this->closed_at?->format('H:i:s d/m/Y'),
            'created_by' => $this->creator?->name ?? 'N/A',
            'updated_by' => $this->editor?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
