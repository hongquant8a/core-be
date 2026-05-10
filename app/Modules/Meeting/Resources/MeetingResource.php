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
                'user_id' => $this->chairperson->user_id,
                'user' => $this->chairperson->user ? [
                    'id' => $this->chairperson->user->id,
                    'name' => $this->chairperson->user->name,
                    'email' => $this->chairperson->user->email,
                    'user_name' => $this->chairperson->user->user_name,
                ] : null,
            ] : null,
            'operator_meeting_attendee_id' => $this->operator_meeting_attendee_id,
            'operator' => $this->operator_meeting_attendee_id && $this->operator ? [
                'id' => $this->operator->id,
                'name' => $this->operator->name,
                'email' => $this->operator->email,
                'position_name' => $this->operator->position_name,
                'department_name' => $this->operator->department_name,
                'user_id' => $this->operator->user_id,
                'user' => $this->operator->user ? [
                    'id' => $this->operator->user->id,
                    'name' => $this->operator->user->name,
                    'email' => $this->operator->user->email,
                    'user_name' => $this->operator->user->user_name,
                ] : null,
            ] : null,
            'title' => $this->title,
            'is_public' => $this->is_public,
            'content' => $this->content,
            'start_time' => $this->start_time?->format('H:i:s d/m/Y'),
            'end_time' => $this->end_time?->format('H:i:s d/m/Y'),
            'status' => $this->status,
            'view_count' => $this->view_count,
            'documents_count' => $this->when(isset($this->documents_count), (int) $this->documents_count),
            'published_at' => $this->published_at?->format('H:i:s d/m/Y'),
            'attendance_locked' => (bool) $this->attendance_locked,
            // FE dùng field này để show/hide button điều hành (end-early, lock-attendance, highlight, vote open/close).
            // Trả về vai trò của user đang request trong meeting này: 'chairperson' | 'operator' | 'participant' | null.
            'current_user_meeting_role' => $request->user() ? $this->resource->userMeetingRole($request->user()) : null,
            // Lưu ý: `checkin_token` (UUID dùng gen QR điểm danh) KHÔNG expose ở đây.
            // Token chỉ truy cập qua endpoint riêng `GET /api/meetings/{id}/qr-token`
            // với permission `meetings.showQrCode` (chỉ chair/op/secretary lấy được).
            'current_meeting_agenda_id' => $this->current_meeting_agenda_id,
            'current_meeting_discussion_registration_id' => $this->current_meeting_discussion_registration_id,
            'current_agenda' => $this->whenLoaded('currentAgenda', fn () => $this->currentAgenda ? new MeetingAgendaResource($this->currentAgenda) : null, null),
            'current_discussion_registration' => $this->whenLoaded('currentDiscussionRegistration', fn () => $this->currentDiscussionRegistration ? new MeetingDiscussionRegistrationResource($this->currentDiscussionRegistration) : null, null),
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
