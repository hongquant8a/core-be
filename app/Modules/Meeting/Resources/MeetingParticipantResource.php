<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_attendee_id' => $this->meeting_attendee_id,
            'attendee_name' => $this->attendee?->name,
            'role' => $this->role,
            'display_name' => $this->display_name,
            'position_name' => $this->position_name,
            'department_name' => $this->department_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'response_status' => $this->response_status,
            'absence_reason' => $this->absence_reason,
            'responded_at' => $this->responded_at?->format('H:i:s d/m/Y'),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
