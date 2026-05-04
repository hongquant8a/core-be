<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_participant_id' => $this->meeting_participant_id,
            'participant_name' => $this->participant?->display_name,
            'status' => $this->status,
            'checkin_method' => $this->checkin_method,
            'checked_in_at' => $this->checked_in_at?->format('H:i:s d/m/Y'),
            'note' => $this->note,
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
