<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingSeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->participant;

        return [
            'id' => $this->id,
            'zone' => $this->zone,
            'is_vip' => $this->is_vip,
            'label' => $this->label,
            'row_index' => $this->row_index,
            'col_index' => $this->col_index,
            'pos_x' => $this->pos_x,
            'pos_y' => $this->pos_y,
            'rotation' => $this->rotation,
            'sort_order' => $this->sort_order,
            'meeting_participant_id' => $this->meeting_participant_id,
            'participant' => $participant ? [
                'id' => $participant->id,
                'meeting_attendee_id' => $participant->meeting_attendee_id,
                'display_name' => $participant->display_name,
                'position_name' => $participant->position_name,
                'department_name' => $participant->department_name,
                'response_status' => $participant->response_status,
                'attendance' => $participant->attendance ? [
                    'status' => $participant->attendance->status,
                ] : null,
            ] : null,
        ];
    }
}
