<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingVoteResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_vote_topic_id' => $this->meeting_vote_topic_id,
            'meeting_participant_id' => $this->meeting_participant_id,
            'participant_name' => $this->participant?->display_name,
            'option' => $this->option,
            'voted_at' => $this->voted_at?->format('H:i:s d/m/Y'),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
