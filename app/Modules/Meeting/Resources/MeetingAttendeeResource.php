<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'meeting_attendee_group_id' => $this->meeting_attendee_group_id,
            'group_name' => $this->group?->name,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'position_name' => $this->position_name,
            'department_name' => $this->department_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $this->creator?->name ?? 'N/A',
            'updated_by' => $this->editor?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
