<?php

namespace App\Modules\Meeting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'meeting_type_id' => $this->meeting_type_id,
            'meeting_type_name' => $this->meetingType?->name,
            'meeting_location_id' => $this->meeting_location_id,
            'meeting_location_name' => $this->meetingLocation?->name,
            'title' => $this->title,
            'is_public' => $this->is_public,
            'content' => $this->content,
            'start_time' => $this->start_time?->format('H:i:s d/m/Y'),
            'end_time' => $this->end_time?->format('H:i:s d/m/Y'),
            'status' => $this->status,
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('H:i:s d/m/Y'),
            'created_by' => $this->creator?->name ?? 'N/A',
            'updated_by' => $this->editor?->name ?? 'N/A',
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            // Nested relations — chỉ trả khi đã load (show endpoint), không có ở index để tránh payload nặng.
            'participants' => MeetingParticipantResource::collection($this->whenLoaded('participants')),
            'agendas' => MeetingAgendaResource::collection($this->whenLoaded('agendas')),
            'documents' => MeetingDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
