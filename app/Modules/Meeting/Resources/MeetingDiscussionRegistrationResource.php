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
            'media_id' => $this->media_id,
            'file_url' => $this->mediaFile?->getUrl(),
            'status' => $this->status,
            'called_at' => $this->called_at?->format('H:i:s d/m/Y'),
            'completed_at' => $this->completed_at?->format('H:i:s d/m/Y'),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
