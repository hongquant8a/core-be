<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Meeting\Concerns\HasDocumentVisibility;
use App\Modules\Meeting\Enums\MeetingStatusEnum;
use App\Modules\Meeting\Exports\MeetingExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingReminder;
use App\Services\Notification\Events\MeetingPublished;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingService
{
    use HasDocumentVisibility;

    public function __construct(private MediaService $mediaService) {}

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
                'reminders',
            ])
            // documents_count: số tài liệu visible cho caller — dùng cho sidebar UI count.
            ->withCount(['documents as documents_count' => $docFilter])
            ->filter($filters)
            ->where(function ($outer) use ($myMeetingIds) {
                // Branch 1: cuộc họp công khai + đã ban hành.
                $outer->where(function ($public) {
                    $public->where('is_public', true)
                        ->whereIn('status', [MeetingStatusEnum::Published->value, MeetingStatusEnum::Completed->value]);
                });

                // Branch 2 (auth): meeting user là chủ trì / thư ký / participant
                // — dùng id list đã pluck từ visibleMeetingIdsForUser thay vì 3 whereHas.
                if (! empty($myMeetingIds)) {
                    $outer->orWhereIn('id', $myMeetingIds);
                }
            });

        // Nếu FE không truyền sort_by -> Áp dụng Bucket Sort mặc định.
        // Nếu FE truyền sort_by (ví dụ: start_time desc) -> Bỏ qua Bucket Sort, dùng sort từ FE.
        if (empty($filters['sort_by'])) {
            $query->reorder()
                // 3 bucket: 0 = Đang diễn ra, 1 = Sắp diễn ra, 2 = Đã kết thúc
                // Đang diễn ra: status = published, start_time <= NOW và (end_time IS NULL hoặc end_time > NOW)
                // Sắp diễn ra: status = published và start_time > NOW
                // Đã kết thúc: status = completed hoặc (status = published và end_time <= NOW)
                ->orderByRaw("
                    CASE
                        WHEN status = 'published' AND start_time <= NOW() AND (end_time IS NULL OR end_time > NOW()) THEN 0
                        WHEN status = 'published' AND start_time > NOW() THEN 1
                        ELSE 2
                    END ASC
                ")
                // Đang diễn ra: cái nào bắt đầu gần NOW nhất trước (DESC)
                ->orderByRaw("CASE WHEN status = 'published' AND start_time <= NOW() AND (end_time IS NULL OR end_time > NOW()) THEN start_time END DESC")
                // Sắp diễn ra: cái nào sắp đến gần nhất trước (ASC)
                ->orderByRaw("CASE WHEN status = 'published' AND start_time > NOW() THEN start_time END ASC")
                // Đã kết thúc: vừa kết thúc gần nhất trước (DESC)
                ->orderByRaw("CASE WHEN status = 'completed' OR (status = 'published' AND end_time <= NOW()) THEN start_time END DESC");
        }

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
     *
     * Thứ tự fallback:
     *  1. auth()->id() — session/default guard
     *  2. Auth::guard('sanctum')->id() — Bearer header
     *  3. Cookie `accessToken` — FE SPA dùng cookie thay vì header. Không có cookie auth
     *     thì auth user qua cookie bị coi như guest → không thấy meeting riêng tư
     *     cross-org mà user có role.
     */
    private function resolveUserId(): ?int
    {
        $id = auth()->id() ?? Auth::guard('sanctum')->id();
        if ($id) {
            return (int) $id;
        }

        $user = $this->resolveUserFromCookieToken();
        if ($user) {
            // setUser vào guard sanctum để các call sau (vd shouldSeeAllDocs trong trait
            // HasDocumentVisibility) cũng pick up đúng user.
            Auth::guard('sanctum')->setUser($user);

            return (int) $user->id;
        }

        return null;
    }

    /**
     * FE SPA dùng cookie `accessToken` (format Sanctum: `id|plain_text`) thay vì Bearer.
     * Resolve thủ công thành User instance. Same logic với MeetingDocumentService —
     * duplicated cho đơn giản, sẽ refactor thành trait nếu xuất hiện call site thứ 3.
     */
    private function resolveUserFromCookieToken(): ?\App\Modules\Core\Models\User
    {
        $request = request();
        if (! $request) {
            return null;
        }
        $token = $request->cookie('accessToken');
        if (! $token || ! str_contains($token, '|')) {
            return null;
        }
        [$id, $plain] = explode('|', $token, 2);
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($id);
        if (! $accessToken) {
            return null;
        }
        if (! hash_equals($accessToken->token, hash('sha256', $plain))) {
            return null;
        }

        return $accessToken->tokenable instanceof \App\Modules\Core\Models\User
            ? $accessToken->tokenable
            : null;
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
            && in_array($meeting->status, [MeetingStatusEnum::Published->value, MeetingStatusEnum::Completed->value], true);

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
            'participants.attendee.user',
            'participants.attendance',
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
            'completed' => (clone $base)->where('status', MeetingStatusEnum::Completed->value)->count(),
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
            'upcoming' => (clone $base)->where('status', MeetingStatusEnum::Published->value)->where('start_time', '>', $now)->count(),
            'in_progress' => (clone $base)
                ->where('status', MeetingStatusEnum::Published->value)
                ->where('start_time', '<=', $now)
                ->count(),
            'finished' => (clone $base)->where('status', MeetingStatusEnum::Completed->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Meeting::with(['meetingType', 'meetingLocation', 'creator.media', 'editor.media', 'reminders'])
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
            'guests',
            'reminders',
        ]);
    }

    public function store(array $validated, ?UploadedFile $projectorImage = null, array $guests = [], array $reminders = []): Meeting
    {
        // guests không phải column trên meetings — strip để không lỗi mass assignment.
        unset($validated['guests']);

        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $projectorImage, $guests, $reminders, &$storedFiles) {
                $payload = [
                    ...$validated,
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                ];
                $meeting = Meeting::create($payload);

                if ($projectorImage) {
                    $media = $this->mediaService->uploadOne($meeting, $projectorImage, Meeting::COLLECTION_PROJECTOR, ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $meeting->update(['projector_image_media_id' => $media->id]);
                }

                if (! empty($guests)) {
                    $this->syncGuests($meeting, $guests);
                }

                if (! empty($reminders)) {
                    $this->syncReminders($meeting, $reminders);
                }

                return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user', 'creator.media', 'editor.media', 'projectorImage', 'guests', 'reminders']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    /**
     * Sao chép cuộc họp — tạo bản copy với tiêu đề có đuôi "(sao chép)".
     *
     * KHÔNG sao chép: tài liệu (documents), chương trình biểu quyết (vote topics).
     * Còn lại sao chép hết.
     *
     * Meeting mới ở trạng thái draft, checkin_token sinh mới qua booted.
     */
    public function duplicate(Meeting $meeting): Meeting
    {
        $copyTitle = $meeting->title . ' (sao chép)';

        return DB::transaction(function () use ($meeting, $copyTitle) {
            // 1. Tạo meeting copy
            $copy = Meeting::create([
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'meeting_type_id' => $meeting->meeting_type_id,
                'meeting_location_id' => $meeting->meeting_location_id,
                'chairperson_meeting_attendee_id' => $meeting->chairperson_meeting_attendee_id,
                'operator_meeting_attendee_id' => $meeting->operator_meeting_attendee_id,
                'title' => $copyTitle,
                'is_public' => $meeting->is_public,
                'content' => $meeting->content,
                'start_time' => $meeting->start_time,
                'end_time' => $meeting->end_time,
                'attendance_open_at' => $meeting->attendance_open_at,
                'attendance_close_at' => $meeting->attendance_close_at,
                'qr_manager_user_id' => $meeting->qr_manager_user_id,
                'projector_image_media_id' => $meeting->projector_image_media_id,
                // Reset runtime fields
                'status' => MeetingStatusEnum::Draft->value,
                'view_count' => 0,
                'published_at' => null,
                'attendance_locked' => false,
                'current_meeting_agenda_id' => null,
                'current_meeting_discussion_registration_id' => null,
            ]);

            // 2. Sao chép khách mời (guests)
            $guests = $meeting->guests()->get();
            foreach ($guests as $g) {
                \App\Modules\Meeting\Models\MeetingGuest::create([
                    'organization_id' => $copy->organization_id,
                    'meeting_id' => $copy->id,
                    'name' => $g->name,
                    'position_name' => $g->position_name,
                    'phone' => $g->phone,
                    'email' => $g->email,
                    'zalo_user_id' => $g->zalo_user_id,
                    'organization_name' => $g->organization_name,
                ]);
            }

            // 3. Sao chép đại biểu (participants)
            $participants = $meeting->participants()->get();
            foreach ($participants as $p) {
                MeetingParticipant::create([
                    'organization_id' => $copy->organization_id,
                    'meeting_id' => $copy->id,
                    'meeting_attendee_id' => $p->meeting_attendee_id,
                    'display_name' => $p->display_name,
                    'position_name' => $p->position_name,
                    'department_name' => $p->department_name,
                    'email' => $p->email,
                    'phone' => $p->phone,
                ]);
            }

            // 4. Sao chép chương trình họp (agendas) — giữ nguyên cấu trúc cây parent_id
            $agendas = $meeting->agendas()->orderBy('sort_order')->orderBy('id')->get();
            $parentMap = [];
            foreach ($agendas as $a) {
                $newAgenda = \App\Modules\Meeting\Models\MeetingAgenda::create([
                    'organization_id' => $copy->organization_id,
                    'meeting_id' => $copy->id,
                    'start_time' => $a->start_time,
                    'end_time' => $a->end_time,
                    'content' => $a->content,
                    'person_in_charge' => $a->person_in_charge,
                    'allow_discussion_registration' => $a->allow_discussion_registration,
                    'discussion_duration_minutes' => $a->discussion_duration_minutes,
                    'allow_question_registration' => $a->allow_question_registration,
                    'question_duration_minutes' => $a->question_duration_minutes,
                    'allow_vote_registration' => $a->allow_vote_registration,
                    'parent_id' => null,
                    'sort_order' => $a->sort_order,
                ]);
                $parentMap[$a->id] = $newAgenda->id;
            }

            foreach ($parentMap as $oldId => $newId) {
                $original = $agendas->firstWhere('id', $oldId);
                if ($original && $original->parent_id && isset($parentMap[$original->parent_id])) {
                    \App\Modules\Meeting\Models\MeetingAgenda::where('id', $newId)
                        ->update(['parent_id' => $parentMap[$original->parent_id]]);
                }
            }

            return $copy->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user', 'creator.media', 'editor.media']);
        });
    }

    public function update(Meeting $meeting, array $validated, ?UploadedFile $projectorImage = null, ?array $guests = null, ?array $reminders = null): Meeting
    {
        unset($validated['guests']);

        $storedFiles = [];
        try {
            return DB::transaction(function () use ($meeting, $validated, $projectorImage, $guests, $reminders, &$storedFiles) {
                $removeProjector = (bool) ($validated['remove_projector_image'] ?? false);
                unset($validated['remove_projector_image']);
                $meeting->update($validated);

                // Xóa ảnh cũ nếu remove flag bật hoặc upload mới (replace).
                if (($removeProjector || $projectorImage) && $meeting->projector_image_media_id) {
                    $this->mediaService->removeByIds($meeting, [$meeting->projector_image_media_id], Meeting::COLLECTION_PROJECTOR);
                    $meeting->update(['projector_image_media_id' => null]);
                }

                if ($projectorImage) {
                    $media = $this->mediaService->uploadOne($meeting, $projectorImage, Meeting::COLLECTION_PROJECTOR, ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $meeting->update(['projector_image_media_id' => $media->id]);
                }

                // guests null → FE không gửi field này → không sync (giữ nguyên).
                // guests = [] → FE muốn xóa toàn bộ guest.
                if ($guests !== null) {
                    $this->syncGuests($meeting, $guests);
                }

                // reminders rỗng hoặc null → không sync (giữ nguyên). Có dữ liệu → sync.
                if (! empty($reminders)) {
                    $this->syncReminders($meeting, $reminders);
                }

                return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user', 'creator.media', 'editor.media', 'projectorImage', 'guests', 'reminders']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    /**
     * Sync danh sách khách mời:
     *   - Item có `id` → update MeetingGuest record (nếu id thuộc meeting này).
     *   - Item không có `id` → tạo MeetingGuest + invitation pending.
     *   - Existing guest KHÔNG có trong list → xóa MeetingGuest (cascade xóa invitation).
     *   - Invitation đã `sent` của guest đã xóa: cascade xóa luôn (nghiệp vụ: nếu admin remove khách thì lịch sử cũng xóa).
     */
    private function syncGuests(Meeting $meeting, array $guests): void
    {
        $orgId = (int) $meeting->organization_id;
        $existing = \App\Modules\Meeting\Models\MeetingGuest::query()
            ->where('meeting_id', $meeting->id)
            ->get();
        $existingIds = $existing->pluck('id')->all();
        $keepIds = [];

        foreach ($guests as $guestData) {
            $payload = [
                'name' => $guestData['name'] ?? null,
                'position_name' => $guestData['position_name'] ?? null,
                'phone' => $guestData['phone'] ?? null,
                'email' => $guestData['email'] ?? null,
                'zalo_user_id' => $guestData['zalo_user_id'] ?? null,
                'organization_name' => $guestData['organization_name'] ?? null,
            ];
            $id = (int) ($guestData['id'] ?? 0);

            if ($id > 0 && in_array($id, $existingIds, true)) {
                // Update existing guest record.
                $existing->firstWhere('id', $id)->update($payload);
                $keepIds[] = $id;
            } else {
                // Create new guest + invitation.
                $guest = \App\Modules\Meeting\Models\MeetingGuest::create([
                    ...$payload,
                    'meeting_id' => $meeting->id,
                    'organization_id' => $orgId,
                ]);
                MeetingInvitation::create([
                    'organization_id' => $orgId,
                    'meeting_id' => $meeting->id,
                    'meeting_guest_id' => $guest->id,
                    'send_type' => 'now',
                    'status' => 'pending',
                ]);
                $keepIds[] = $guest->id;
            }
        }

        // Xóa guest không còn trong list (cascade xóa invitations).
        $toDelete = array_diff($existingIds, $keepIds);
        if (! empty($toDelete)) {
            \App\Modules\Meeting\Models\MeetingGuest::whereIn('id', $toDelete)->delete();
        }
    }

    private function syncReminders(Meeting $meeting, array $reminders): void
    {
        $keptIds = [];
        foreach ($reminders as $r) {
            $reminderType = $r['reminder_type'] ?? 'scheduled';
            $channels = array_values(array_unique(array_map('strtoupper', (array) ($r['channels'] ?? []))));

            if ($reminderType === 'instant') {
                $attributes = [
                    'organization_id' => (int) $meeting->organization_id,
                    'meeting_id'      => $meeting->id,
                    'reminder_type'   => 'instant',
                    'moment'          => null,
                    'offset_minutes'  => 0,
                    'channels'        => $channels,
                    'source'          => 'CUSTOM',
                    'status'          => 'active',
                    'remind_at'       => null,
                    'scheduled_at'    => null,
                ];
            } else {
                $moment = $r['moment'] ?? 'before';
                $offset = (int) ($r['offset_minutes'] ?? 0);

                $remindAt = $meeting->start_time
                    ? match ($moment) {
                        'before' => $offset ? $meeting->start_time->copy()->subMinutes($offset) : null,
                        'on'     => $meeting->start_time->copy(),
                        'after'  => $offset ? $meeting->start_time->copy()->addMinutes($offset) : null,
                        default  => null,
                    }
                    : null;

                $attributes = [
                    'organization_id' => (int) $meeting->organization_id,
                    'meeting_id'      => $meeting->id,
                    'reminder_type'   => 'scheduled',
                    'moment'          => $moment,
                    'offset_minutes'  => $offset,
                    'channels'        => $channels,
                    'source'          => 'CUSTOM',
                    'status'          => 'pending',
                    'remind_at'       => $remindAt,
                    'scheduled_at'    => $remindAt,
                ];
            }

            if (! empty($r['id'])) {
                $existing = MeetingReminder::where('id', $r['id'])
                    ->where('meeting_id', $meeting->id)
                    ->where('source', 'CUSTOM')
                    ->first();
                if ($existing) {
                    if ($existing->status !== 'pending') {
                        $attributes['status'] = $existing->status;
                    }
                    $existing->update($attributes);
                    $keptIds[] = $existing->id;
                } else {
                    $new = MeetingReminder::create($attributes);
                    $keptIds[] = $new->id;
                }
            } else {
                $new = MeetingReminder::create($attributes);
                $keptIds[] = $new->id;
            }
        }

        // Xóa CUSTOM reminders không còn trong payload.
        $meeting->reminders()->where('source', 'CUSTOM')
            ->whereNotIn('id', $keptIds)
            ->delete();
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

        DB::transaction(function () use ($meeting, $status, $previous) {
            $payload = ['status' => $status];

            // Auto-set published_at lần đầu publish; republish sau đó không ghi đè (giữ mốc gốc).
            if ($status === MeetingStatusEnum::Published->value && $meeting->published_at === null) {
                $payload['published_at'] = now();
            }

            $meeting->update($payload);

            if ($status === MeetingStatusEnum::Published->value) {
                $this->createInvitationsForParticipants($meeting);

                // Re-publish (cancelled → published): reset invitation status về pending
                // để listener MeetingPublished gửi lại thông báo cho toàn bộ người tham gia.
                if ($previous === MeetingStatusEnum::Cancelled->value) {
                    MeetingInvitation::where('meeting_id', $meeting->id)
                        ->where('organization_id', $meeting->organization_id)
                        ->whereIn('status', ['sent', 'failed'])
                        ->update(['status' => 'pending', 'sent_at' => null]);
                }
            }
        });

        if ($previous !== MeetingStatusEnum::Published->value
            && $status === MeetingStatusEnum::Published->value) {
            Event::dispatch(new MeetingPublished($meeting->fresh()));
        }

        // Notification khi hủy meeting đã ban hành. KHÔNG bắn khi chuyển draft → cancelled
        // (chưa publish → chưa ai nhận được lời mời, không cần thông báo hủy).
        if ($previous === MeetingStatusEnum::Published->value
            && $status === MeetingStatusEnum::Cancelled->value) {
            Event::dispatch(new \App\Services\Notification\Events\MeetingCancelled($meeting->fresh()));
        }

        return $meeting->load(['meetingType', 'meetingLocation', 'creator.media', 'editor.media', 'reminders']);
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
     * Operator kết thúc cuộc họp — set status = completed.
     * Hóa các hành động (khóa điểm danh, biểu quyết, thảo luận...).
     */
    public function endEarly(Meeting $meeting): Meeting
    {
        $meeting->update(['status' => MeetingStatusEnum::Completed->value]);

        broadcast(new \App\Modules\Meeting\Events\MeetingEndedEarly($meeting))->toOthers();

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson.user', 'operator.user']);
    }

    /**
     * Operator mở lại cuộc họp — set status = published.
     */
    public function reopen(Meeting $meeting): Meeting
    {
        $meeting->update(['status' => MeetingStatusEnum::Published->value]);

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

        $autoCompletedPrevId = null;
        DB::transaction(function () use ($meeting, $discussionRegistrationId, &$autoCompletedPrevId) {
            // Khi chuyển người phát biểu, registration cũ (nếu vẫn `registered`)
            // tự động đánh dấu hoàn thành — giả định lượt phát biểu đã kết thúc khi
            // operator/chair chuyển sang người tiếp theo (hoặc bỏ highlight).
            $prevId = $meeting->current_meeting_discussion_registration_id;
            if ($prevId && $prevId !== $discussionRegistrationId) {
                $prev = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::find($prevId);
                if ($prev) {
                    $updates = ['highlighted_at' => null];
                    if ($prev->status === \App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum::Registered->value) {
                        $updates['status'] = \App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum::Completed->value;
                        $updates['completed_at'] = now();
                        $autoCompletedPrevId = $prev->id;
                    }
                    $prev->update($updates);
                }
            }

            // Set highlighted_at = now() cho registration mới.
            if ($discussionRegistrationId) {
                \App\Modules\Meeting\Models\MeetingDiscussionRegistration::where('id', $discussionRegistrationId)
                    ->update(['highlighted_at' => now()]);
            }

            $meeting->update(['current_meeting_discussion_registration_id' => $discussionRegistrationId]);
        });

        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionHighlighted($meeting, $autoCompletedPrevId))->toOthers();

        return $meeting->load([
            'meetingType', 'meetingLocation', 'chairperson.user', 'operator.user',
            'currentAgenda', 'currentDiscussionRegistration',
        ]);
    }

    public function export(array $filters, ?string $fileName = null): BinaryFileResponse
    {
        return Excel::download(new MeetingExport($filters), $fileName ?? ExportFilename::make('cuoc-hop'));
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
