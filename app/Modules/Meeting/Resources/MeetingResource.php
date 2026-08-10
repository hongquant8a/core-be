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
            'meeting_location_google_maps_url' => $this->meetingLocation?->google_maps_url,
            'chairperson_meeting_attendee_id' => $this->chairperson_meeting_attendee_id,
            'chairperson' => $this->whenLoaded('chairperson', fn () => [
                'id' => $this->chairperson->id,
                'name' => $this->chairperson->name,
                'email' => $this->chairperson->email,
                'position_name' => $this->chairperson->position_name,
                'department_name' => $this->chairperson->department_name,
                'user_id' => $this->chairperson->user_id,
            ]),
            'operator_meeting_attendee_id' => $this->operator_meeting_attendee_id,
            'operator' => $this->whenLoaded('operator', fn () => [
                'id' => $this->operator->id,
                'name' => $this->operator->name,
                'email' => $this->operator->email,
                'position_name' => $this->operator->position_name,
                'department_name' => $this->operator->department_name,
                'user_id' => $this->operator->user_id,
            ]),
            'title' => $this->title,
            'is_public' => $this->is_public,
            'has_online_room' => (bool) $this->has_online_room,
            'content' => $this->content,
            'start_time' => $this->start_time?->format('H:i:s d/m/Y'),
            'end_time' => $this->end_time?->format('H:i:s d/m/Y'),
            // Khung giờ điểm danh — FE check để show/hide nút điểm danh.
            // ISO format để FE compare với now() chuẩn timezone.
            'attendance_open_at' => $this->attendance_open_at?->toIso8601String(),
            'attendance_close_at' => $this->attendance_close_at?->toIso8601String(),
            'status' => $this->status,
            'view_count' => $this->view_count,
            'documents_count' => $this->when(isset($this->documents_count), (int) $this->documents_count),
            'published_at' => $this->published_at?->format('H:i:s d/m/Y'),
            'attendance_locked' => (bool) $this->attendance_locked,
            'allow_host_management' => (bool) $this->allow_host_management,
            // Khi true, đại biểu tự điểm danh được duyệt (present) ngay, không cần điều hành xác nhận.
            'auto_confirm_attendance' => (bool) $this->auto_confirm_attendance,
            // FE dùng field này để show/hide button điều hành (end-early, lock-attendance, highlight, vote open/close).
            // Vai trò CHÍNH ưu tiên: FK chair > FK operator > participant entry. Chair có participant entry vẫn trả 'chairperson'.
            // Dùng Auth::guard('sanctum') fallback cho public route không có middleware auth:sanctum.
            'current_user_meeting_role' => ($user = ($request->user() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->user())) ? $this->resource->userMeetingRole($user) : null,
            // Đại biểu đã được xác nhận điểm danh (attendance present).
            'is_attendance_confirmed' => $user ? $this->resource->canVote($user) : false,
            // Người được gán làm quản lý QR điểm danh (qua Tab cấu hình meeting).
            // Khi set → user này có quyền GET /api/meetings/{id}/qr-token bất kể có role
            // chair/op không. Gate qua MeetingPolicy::showQrCode (theo khóa ngoại, không Spatie).
            'qr_manager_user_id' => $this->qr_manager_user_id,
            'qr_manager' => $this->qr_manager_user_id && $this->qrManager ? [
                'id' => $this->qrManager->id,
                'name' => $this->qrManager->name,
                'email' => $this->qrManager->email,
                'user_name' => $this->qrManager->user_name,
            ] : null,
            // Background riêng cho meeting (Tab 8 màn chiếu). Null → FE fallback sang
            // MeetingSetting.projector_image của tổ chức (gọi GET /api/meeting-settings).
            'projector_image_media_id' => $this->projector_image_media_id,
            'projector_image_url' => $this->projector_image_media_id && $this->projectorImage
                ? '/storage/'.$this->projectorImage->id.'/'.$this->projectorImage->file_name
                : null,
            // Ảnh chờ chương trình riêng cho meeting. Null → FE fallback MeetingSetting.
            'waiting_image_media_id' => $this->waiting_image_media_id,
            'waiting_image_url' => $this->waiting_image_media_id && $this->waitingImage
                ? '/storage/'.$this->waitingImage->id.'/'.$this->waitingImage->file_name
                : null,
            // Lưu ý: `checkin_token` (UUID dùng gen QR điểm danh) KHÔNG expose ở đây.
            // Token chỉ truy cập qua endpoint riêng `GET /api/meetings/{id}/qr-token`
            // với gate showQrCode (chair OR operator OR qr_manager_user_id).
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
            // Reminder per-record — chỉ trả CUSTOM, PRESET là nội bộ hệ thống.
            'reminders' => $this->whenLoaded('reminders', function () {
                return MeetingReminderResource::collection(
                    $this->reminders->where('source', 'CUSTOM')
                );
            }),
            // Khách mời (input trực tiếp khi tạo meeting).
            'guests' => $this->whenLoaded('guests', fn () => $this->guests->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'position_name' => $g->position_name,
                'phone' => $g->phone,
                'email' => $g->email,
                'zalo_user_id' => $g->zalo_user_id,
                'organization_name' => $g->organization_name,
                'invited_at' => $g->invited_at?->format('H:i:s d/m/Y'),
            ])),
        ];
    }
}
