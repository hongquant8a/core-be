<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAgendaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'meeting_id' => $this->meeting_id,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'content' => $this->content,
            'script' => $this->script,
            'person_in_charge' => $this->person_in_charge,
            'allow_discussion_registration' => $this->allow_discussion_registration,
            'discussion_duration_minutes' => $this->discussion_duration_minutes,
            'allow_question_registration' => $this->allow_question_registration,
            'question_duration_minutes' => $this->question_duration_minutes,
            'allow_vote_registration' => $this->allow_vote_registration,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
