<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Concerns\HasDocumentVisibility;
use App\Modules\Meeting\Enums\MeetingStatusEnum;
use App\Modules\Meeting\Exports\MeetingExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Services\Notification\Events\MeetingPublished;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingService
{
    use HasDocumentVisibility;

    /**
     * "Visible" index — endpoint dùng chung cho trang index FE:
     *   - Guest: chỉ thấy meeting (is_public=true + status=published)
     *   - Auth user: thấy meeting công khai + meeting họ là chủ trì / thư ký / participant
     *
     * Preload: documents (filter is_public theo participation) + participants.attendee.
     */
    public function publicIndex(array $filters, int $limit)
    {
        $userId = $this->resolveUserId();
        $myMeetingIds = $userId ? $this->visibleMeetingIdsForUser($userId) : [];

        // Doc filter dùng chung cho preload + count: is_public=true HOẶC thuộc meeting user tham gia.
        $docFilter = function ($q) use ($myMeetingIds) {
            $q->where(function ($sub) use ($myMeetingIds) {
                $sub->where('is_public', true);
                if (! empty($myMeetingIds)) {
                    $sub->orWhereIn('meeting_id', $myMeetingIds);
                }
            });
        };

        $query = Meeting::with([
                'meetingType',
                'meetingLocation',
                'chairperson.user',
                'operator.user',
                'documents' => $docFilter,
                'documents.documentType',
                'documents.mediaFile',
                'participants.attendee',
            ])
            // documents_count: số tài liệu visible cho caller — dùng cho sidebar UI count.
            ->withCount(['documents as documents_count' => $docFilter])
            ->filter($filters)
            ->where(function ($outer) use ($myMeetingIds) {
                // Branch 1: cuộc họp công khai + đã ban hành.
                $outer->where(function ($public) {
                    $public->where('is_public', true)
                        ->where('status', MeetingStatusEnum::Published->value);
                });

                // Branch 2 (auth): meeting user là chủ trì / thư ký / participant
                // — dùng id list đã pluck từ visibleMeetingIdsForUser thay vì 3 whereHas.
                if (! empty($myMeetingIds)) {
                    $outer->orWhereIn('id', $myMeetingIds);
                }
            });

        return $query->paginate($limit);
    }

    /**
     * IDs của meeting user là chủ trì / thư ký / participant — dùng để filter doc preload
     * trong index list (nơi không thể check per-meeting trong eager closure).
     */
    private function visibleMeetingIdsForUser(int $userId): array
    {
        // Loại draft hoàn toàn — chair/operator xem draft qua admin endpoint /api/meetings.
        return Meeting::query()
            ->where('status', '!=', MeetingStatusEnum::Draft->value)
            ->where(function ($q) use ($userId) {
                $q->whereHas('chairperson', fn ($a) => $a->where('user_id', $userId))
                    ->orWhereHas('operator', fn ($a) => $a->where('user_id', $userId))
                    ->orWhereHas('participants.attendee', fn ($a) => $a->where('user_id', $userId));
            })
            ->pluck('id')
            ->all();
    }

    /**
     * Resolve auth user id — ép guard sanctum để pick up Bearer token kể cả khi route
     * không có middleware auth:sanctum (vd /meetings/public). auth()->id() default guard
     * không resolve được token vì Sanctum chỉ activate khi middleware chạy.
     */
    private function resolveUserId(): ?int
    {
        return auth()->id() ?? Auth::guard('sanctum')->id();
    }

    /**
     * "Visible" show — endpoint dùng chung:
     *   - Public + published meeting: ai cũng xem được
     *   - Meeting riêng tư: chỉ chủ trì / thư ký / participant xem được
     * Documents preload filter is_public=true cho user không phải participant; participant thấy hết.
     */
    public function publicShow(Meeting $meeting): Meeting
    {
        $isParticipant = $this->shouldSeeAllDocs($meeting);
        $isPublishedPublic = $meeting->is_public
            && $meeting->status === MeetingStatusEnum::Published->value;

        if (! $isParticipant && ! $isPublishedPublic) {
            throw new ModelNotFoundException('Không tìm thấy cuộc họp.');
        }

        // view_count + meeting_views log → middleware count.meeting.view xử lý (dedupe per user/day).
        return $meeting->load([
            'meetingType',
            'meetingLocation',
            'chairperson.user',
            'operator.user',
            'agendas',
            'documents' => fn ($q) => $isParticipant ? $q : $q->where('is_public', true),
            'documents.documentType',
            'documents.mediaFile',
            'participants.attendee',
            'voteTopics',
            'voteTopics.userResponses' => \App\Modules\Meeting\Services\MeetingVoteTopicService::userResponsesEagerLoad(),
            'currentAgenda',
            'currentDiscussionRegistration',
        ]);
    }

    public function stats(array $filters): array
    {
        $base = Meeting::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->where('status', MeetingStatusEnum::Published->value)->count(),
            'draft' => (clone $base)->where('status', MeetingStatusEnum::Draft->value)->count(),
        ];
    }

    /**
     * Stats cho trang công khai + đại biểu — phase derived từ start_time/end_time vs now.
     * Scope: meeting public-published HOẶC meeting user là chair/operator/participant (nếu auth).
     */
    public function publicStats(array $filters): array
    {
        $userId = $this->resolveUserId();
        $myMeetingIds = $userId ? $this->visibleMeetingIdsForUser($userId) : [];

        $base = Meeting::query()
            ->filter($filters)
            ->where(function ($outer) use ($myMeetingIds) {
                $outer->where(function ($public) {
                    $public->where('is_public', true)
                        ->where('status', MeetingStatusEnum::Published->value);
                });
                if (! empty($myMeetingIds)) {
                    $outer->orWhereIn('id', $myMeetingIds);
                }
            });

        $now = now();

        return [
            'total' => (clone $base)->count(),
            'upcoming' => (clone $base)->where('start_time', '>', $now)->count(),
            'in_progress' => (clone $base)
                ->where('start_time', '<=', $now)
                ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>=', $now))
                ->count(),
            'finished' => (clone $base)->whereNotNull('end_time')->where('end_time', '<', $now)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Meeting::with(['meetingType', 'meetingLocation', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Meeting $meeting): Meeting
    {
        // Auth show endpoint (CRUD) — caller có permission meetings.show, không filter
        // is_public. Filter is_public chỉ áp dụng cho publicShow/publicIndex.
        return $meeting->load([
            'meetingType',
            'meetingLocation',
            'chairperson.user',
            'operator.user',
            'creator.media',
            'editor.media',
            'participants.attendee.user',
            'agendas',
            'documents',
            'documents.documentType',
            'documents.mediaFile',
            'voteTopics',
            'voteTopics.userResponses' => \App\Modules\Meeting\Services\MeetingVoteTopicService::userResponsesEagerLoad(),
            'currentAgenda',
            'currentDiscussionRegistration',
        ]);
    }

    public function store(array $validated): Meeting
    {
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return Meeting::create($payload)->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user', 'creator.media', 'editor.media']);
    }

    public function update(Meeting $meeting, array $validated): Meeting
    {
        $meeting->update($validated);

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user', 'creator.media', 'editor.media']);
    }

    public function destroy(Meeting $meeting): void
    {
        $meeting->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(Meeting $meeting, string $status): Meeting
    {
        $previous = $meeting->status;

        DB::transaction(function () use ($meeting, $status) {
            $payload = ['status' => $status];

            // Auto-set published_at lần đầu publish; republish sau đó không ghi đè (giữ mốc gốc).
            if ($status === MeetingStatusEnum::Published->value && $meeting->published_at === null) {
                $payload['published_at'] = now();
            }

            $meeting->update($payload);

            if ($status === MeetingStatusEnum::Published->value) {
                $this->createInvitationsForParticipants($meeting);
            }
        });

        if ($previous !== MeetingStatusEnum::Published->value
            && $status === MeetingStatusEnum::Published->value) {
            Event::dispatch(new MeetingPublished($meeting->fresh()));
        }

        return $meeting->load(['meetingType', 'meetingLocation', 'creator.media', 'editor.media']);
    }

    /**
     * Tạo invitation cho participant + chủ trì + thư ký — idempotent.
     * Recipient = participant (đại biểu) HOẶC attendee trực tiếp (chair/operator).
     */
    private function createInvitationsForParticipants(Meeting $meeting): void
    {
        $participantIds = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $meeting->organization_id)
            ->pluck('id')
            ->all();

        $existingParticipantIds = MeetingInvitation::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('meeting_participant_id')
            ->pluck('meeting_participant_id')
            ->all();

        foreach ($participantIds as $participantId) {
            if (in_array($participantId, $existingParticipantIds, true)) {
                continue;
            }
            MeetingInvitation::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'meeting_participant_id' => $participantId,
                'send_type' => 'now',
                'status' => 'pending',
            ]);
        }

        // Chủ trì + thư ký — gửi giấy mời qua attendee_id (không qua participants).
        $chairOpAttendeeIds = array_values(array_filter([
            $meeting->chairperson_meeting_attendee_id,
            $meeting->operator_meeting_attendee_id,
        ]));
        if (empty($chairOpAttendeeIds)) {
            return;
        }

        // Tránh trùng nếu chair/operator đồng thời là 1 participant đã được mời.
        $participantAttendeeIds = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_attendee_id', $chairOpAttendeeIds)
            ->pluck('meeting_attendee_id')
            ->all();

        $existingAttendeeIds = MeetingInvitation::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_attendee_id', $chairOpAttendeeIds)
            ->pluck('meeting_attendee_id')
            ->all();

        foreach (array_unique($chairOpAttendeeIds) as $attendeeId) {
            if (in_array($attendeeId, $participantAttendeeIds, true) || in_array($attendeeId, $existingAttendeeIds, true)) {
                continue;
            }
            MeetingInvitation::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'meeting_attendee_id' => $attendeeId,
                'send_type' => 'now',
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Operator kết thúc cuộc họp sớm — set end_time = now() để FE phase derive thành "finished".
     * Chặn override khi đã quá end_time dự kiến (tránh thay đổi data lịch sử).
     */
    public function endEarly(Meeting $meeting): Meeting
    {
        if ($meeting->end_time !== null && $meeting->end_time->lte(now())) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'end_time' => ['Cuộc họp đã quá giờ kết thúc dự kiến — không cần kết thúc sớm.'],
            ]);
        }

        $meeting->update(['end_time' => now()]);

        broadcast(new \App\Modules\Meeting\Events\MeetingEndedEarly($meeting))->toOthers();

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user']);
    }

    /**
     * Operator khoá danh sách điểm danh — đại biểu không thể tự checkin/báo vắng nữa.
     */
    public function lockAttendance(Meeting $meeting): Meeting
    {
        $meeting->update(['attendance_locked' => true]);

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user']);
    }

    /**
     * Operator mở khoá điểm danh.
     */
    public function unlockAttendance(Meeting $meeting): Meeting
    {
        $meeting->update(['attendance_locked' => false]);

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user']);
    }

    /**
     * Operator highlight 1 chương trình lên màn chiếu (Tab 8). Truyền null để bỏ highlight.
     * Validate agenda_id phải thuộc đúng meeting (chặn cross-meeting).
     */
    public function highlightAgenda(Meeting $meeting, ?int $agendaId): Meeting
    {
        if ($agendaId !== null) {
            $exists = $meeting->agendas()->whereKey($agendaId)->exists();
            if (! $exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'agenda_id' => ['Chương trình không thuộc cuộc họp này.'],
                ]);
            }
        }

        $meeting->update(['current_meeting_agenda_id' => $agendaId]);

        broadcast(new \App\Modules\Meeting\Events\MeetingAgendaHighlighted($meeting))->toOthers();

        return $meeting->load([
            'meetingType', 'meetingLocation', 'chairperson.user', 'operator.user',
            'currentAgenda', 'currentDiscussionRegistration',
        ]);
    }

    /**
     * Operator highlight 1 đăng ký phát biểu/chất vấn lên màn chiếu. Truyền null để bỏ highlight.
     * Validate registration phải thuộc đúng meeting.
     */
    public function highlightDiscussion(Meeting $meeting, ?int $discussionRegistrationId): Meeting
    {
        if ($discussionRegistrationId !== null) {
            $exists = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
                ->where('id', $discussionRegistrationId)
                ->where('meeting_id', $meeting->id)
                ->exists();
            if (! $exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'discussion_registration_id' => ['Đăng ký phát biểu không thuộc cuộc họp này.'],
                ]);
            }
        }

        DB::transaction(function () use ($meeting, $discussionRegistrationId) {
            // Clear highlighted_at của registration cũ (nếu có) — 1 highlight/meeting tại 1 thời điểm.
            $prevId = $meeting->current_meeting_discussion_registration_id;
            if ($prevId && $prevId !== $discussionRegistrationId) {
                \App\Modules\Meeting\Models\MeetingDiscussionRegistration::where('id', $prevId)
                    ->update(['highlighted_at' => null]);
            }

            // Set highlighted_at = now() cho registration mới.
            if ($discussionRegistrationId) {
                \App\Modules\Meeting\Models\MeetingDiscussionRegistration::where('id', $discussionRegistrationId)
                    ->update(['highlighted_at' => now()]);
            }

            $meeting->update(['current_meeting_discussion_registration_id' => $discussionRegistrationId]);
        });

        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionHighlighted($meeting))->toOthers();

        return $meeting->load([
            'meetingType', 'meetingLocation', 'chairperson.user', 'operator.user',
            'currentAgenda', 'currentDiscussionRegistration',
        ]);
    }

    public function export(array $filters, string $fileName = 'meetings.xlsx'): BinaryFileResponse
    {
        return Excel::download(new MeetingExport($filters), $fileName);
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
