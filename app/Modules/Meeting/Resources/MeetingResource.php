<?php

namespace App\Modules\Meeting\Resources;

use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    use FormatsUserSummary;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'meeting_type_id' => $this->meeting_type_id,
            'meeting_type_name' => $this->meetingType?->name,
            'meeting_location_id' => $this->meeting_location_id,
            'meeting_location_name' => $this->meetingLocation?->name,
            'chairperson_meeting_attendee_id' => $this->chairperson_meeting_attendee_id,
            'chairperson' => $this->chairperson_meeting_attendee_id && $this->chairperson ? [
                'id' => $this->chairperson->id,
                'name' => $this->chairperson->name,
                'email' => $this->chairperson->email,
                'position_name' => $this->chairperson->position_name,
                'department_name' => $this->chairperson->department_name,
            ] : null,
            'operator_meeting_attendee_id' => $this->operator_meeting_attendee_id,
            'operator' => $this->operator_meeting_attendee_id && $this->operator ? [
                'id' => $this->operator->id,
                'name' => $this->operator->name,
                'email' => $this->operator->email,
                'position_name' => $this->operator->position_name,
                'department_name' => $this->operator->department_name,
            ] : null,
            'title' => $this->title,
            'is_public' => $this->is_public,
            'content' => $this->content,
            'start_time' => $this->start_time?->format('H:i:s d/m/Y'),
            'end_time' => $this->end_time?->format('H:i:s d/m/Y'),
            'status' => $this->status,
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('H:i:s d/m/Y'),
            'attendance_locked' => (bool) $this->attendance_locked,
            'runtime_started_at' => $this->runtime_started_at?->format('H:i:s d/m/Y'),
            'runtime_paused_at' => $this->runtime_paused_at?->format('H:i:s d/m/Y'),
            'runtime_ended_at' => $this->runtime_ended_at?->format('H:i:s d/m/Y'),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'created_at' => $this->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $this->updated_at?->format('H:i:s d/m/Y'),

            // Nested relations — chỉ trả khi đã load (show endpoint), không có ở index để tránh payload nặng.
            'participants' => MeetingParticipantResource::collection($this->whenLoaded('participants')),
            'agendas' => MeetingAgendaResource::collection($this->whenLoaded('agendas')),
            'documents' => MeetingDocumentResource::collection($this->whenLoaded('documents')),
            'vote_topics' => MeetingVoteTopicResource::collection($this->whenLoaded('voteTopics')),
        ];
    }
}
