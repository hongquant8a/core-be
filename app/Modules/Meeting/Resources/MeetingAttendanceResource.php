<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->participant;

        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_participant_id' => $this->meeting_participant_id,
            'meeting_attendee_id' => $participant?->meeting_attendee_id,
            'attendee_name' => $participant?->attendee?->name,
            'display_name' => $participant?->display_name,
            'participant_name' => $participant?->display_name,
            'position_name' => $participant?->position_name,
            'department_name' => $participant?->department_name,
            'email' => $participant?->email,
            'phone' => $participant?->phone,
            'response_status' => $participant?->response_status,
            'status' => $this->status,
            'checkin_method' => $this->checkin_method,
            'checked_in_at' => $this->checked_in_at?->format('H:i:s d/m/Y'),
            'note' => $this->note,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
