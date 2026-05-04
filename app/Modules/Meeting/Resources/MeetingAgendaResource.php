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
            'person_in_charge' => $this->person_in_charge,
            'allow_discussion_registration' => $this->allow_discussion_registration,
            'allow_question_registration' => $this->allow_question_registration,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
