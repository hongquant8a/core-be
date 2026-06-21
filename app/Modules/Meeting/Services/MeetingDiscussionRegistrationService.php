<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Enums\MeetingDiscussionTypeEnum;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Đăng ký thảo luận / chất vấn — quy tắc theo spec phòng họp không giấy:
 *   - Đại biểu (auth user là participant) tạo đăng ký cho chính mình + sửa/xóa của mình.
 *   - State machine nhị phân: registered (chưa phát biểu) -> completed (đã phát biểu).
 *   - Operator đánh dấu hoàn thành qua endpoint /complete.
 *   - Đại biểu rút đăng ký TRƯỚC khi được gọi xong = DELETE row.
 *   - Index/show/stats: ai xem được meeting đều xem được (FE quyết định visibility).
 */
class MeetingDiscussionRegistrationService
{
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters, ?Meeting $meeting = null): array
    {
        $base = MeetingDiscussionRegistration::filter($filters);

        if ($meeting) {
            $this->applyVisibilityScope($base, $meeting);
        }

        $countByType = function (string $type) use ($base) {
            $scoped = (clone $base)->where('type', $type);

            return [
                'total' => (clone $scoped)->count(),
                'registered' => (clone $scoped)->where('status', MeetingDiscussionStatusEnum::Registered->value)->count(),
                'completed' => (clone $scoped)->where('status', MeetingDiscussionStatusEnum::Completed->value)->count(),
            ];
        };

        return [
            'total' => (clone $base)->count(),
            'registered' => (clone $base)->where('status', MeetingDiscussionStatusEnum::Registered->value)->count(),
            'completed' => (clone $base)->where('status', MeetingDiscussionStatusEnum::Completed->value)->count(),
            'discussion' => $countByType(MeetingDiscussionTypeEnum::Discussion->value),
            'question' => $countByType(MeetingDiscussionTypeEnum::Question->value),
        ];
    }

    public function index(array $filters, int $limit, ?Meeting $meeting = null)
    {
        $query = MeetingDiscussionRegistration::with(['participant.attendance', 'agenda', 'mediaFile', 'attachments.mediaFile'])
            ->filter($filters);

        if ($meeting) {
            $this->applyVisibilityScope($query, $meeting);
        }

        return $query->paginate($limit);
    }

    public function show(MeetingDiscussionRegistration $meetingDiscussionRegistration): MeetingDiscussionRegistration
    {
        return $meetingDiscussionRegistration->load(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile', 'answerAttachment']);
    }

    public function store(array $validated, $file = null): MeetingDiscussionRegistration
    {
        $userId = $this->resolveCurrentUserId();
        $meetingId = (int) $validated['meeting_id'];
        $type = $validated['type'];
        $agendaId = (int) $validated['meeting_agenda_id'];

        $meeting = Meeting::findOrFail($meetingId);
        $user = auth()->user();
        $isOperator = $user ? in_array($meeting->userMeetingRole($user), ['chairperson', 'operator']) : false;

        if ($isOperator && !empty($validated['meeting_participant_id'])) {
            $participant = MeetingParticipant::query()
                ->where('meeting_id', $meetingId)
                ->where('id', $validated['meeting_participant_id'])
                ->first();
            if (! $participant) {
                throw new ModelNotFoundException('Không tìm thấy đại biểu được chỉ định trong cuộc họp này.');
            }
        } else {
            // Auto-derive meeting_participant_id từ auth user
            $participant = MeetingParticipant::query()
                ->where('meeting_id', $meetingId)
                ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
                ->first();

            if (! $participant) {
                throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
            }
        }


        // Spec line 276: BE kiểm tra cờ cho phép theo agenda.
        // Đăng ký luôn gắn với 1 chương trình cụ thể (agenda_id required).
        $agenda = MeetingAgenda::query()
            ->where('meeting_id', $meetingId)
            ->find($agendaId);
        if (! $agenda) {
            throw new ModelNotFoundException('Chương trình họp không thuộc cuộc họp này.');
        }
        $flag = $type === MeetingDiscussionTypeEnum::Discussion->value
            ? 'allow_discussion_registration'
            : 'allow_question_registration';
        if (! $agenda->{$flag}) {
            $label = $type === MeetingDiscussionTypeEnum::Discussion->value ? 'thảo luận' : 'chất vấn';
            throw ValidationException::withMessages([
                'meeting_agenda_id' => ["Chương trình họp này không cho phép đăng ký {$label}."],
            ]);
        }

        $storedFiles = [];
        try {
            $model = DB::transaction(function () use ($validated, $file, $participant, $meetingId, $type, $agendaId, &$storedFiles) {
                $payload = [
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                    'meeting_id' => $meetingId,
                    'meeting_participant_id' => $participant->id,
                    'meeting_agenda_id' => $agendaId,
                    'type' => $type,
                    'content' => $validated['content'],
                    'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($meetingId, $agendaId, $type),
                    'status' => $validated['status'] ?? MeetingDiscussionStatusEnum::Registered->value,
                    'is_public' => $validated['is_public'] ?? true,
                ];

                $created = MeetingDiscussionRegistration::create($payload);

                if ($file) {
                    $media = $this->mediaService->uploadOne($created, $file, 'meeting-discussion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $created->update(['media_id' => $media->id]);
                }

                return $created->load(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }

        // Broadcast sau transaction để đảm bảo data đã commit.
        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionRegistrationCreated($model))->toOthers();

        return $model;
    }

    public function update(MeetingDiscussionRegistration $model, array $validated, $file = null): MeetingDiscussionRegistration
    {
        // Phân quyền:
        //   - Owner đại biểu (auth = participant.attendee.user_id): update content/attachment/status/sort_order.
        //   - Chair/Operator của meeting (MeetingPolicy::operate): update operator_note (ghi chú thảo luận)
        //     và answer_content (nội dung trả lời chất vấn) và answer_attachment (file đính kèm trả lời).
        //   - Cả 2 vai trò có thể giao nhau (vd chair tự đăng ký phát biểu).
        $userId = $this->resolveCurrentUserId();
        $model->loadMissing(['participant.attendee', 'meeting.chairperson', 'meeting.operator']);

        $isOwner = (int) ($model->participant?->attendee?->user_id ?? 0) === (int) $userId;
        $isChair = (int) ($model->meeting?->chairperson?->user_id ?? 0) === (int) $userId;
        $isOperator = (int) ($model->meeting?->operator?->user_id ?? 0) === (int) $userId;
        $canOperate = $isChair || $isOperator;

        if (! $isOwner && ! $canOperate) {
            throw new ModelNotFoundException('Không tìm thấy đăng ký.');
        }

        // Filter field theo vai trò:
        //   Non-owner && Non-operator -> 404 ở trên.
        //   Non-operator    -> không được set operator_note + answer_content + answer_attachment (strip).
        //   Operator        -> được phép sửa toàn bộ (bao gồm content, attachment của người khác).
        $operatorOnlyFields = ['operator_note', 'answer_content', 'answer_attachment', 'remove_answer_attachment'];

        if (! $canOperate) {
            foreach ($operatorOnlyFields as $f) {
                unset($validated[$f]);
            }
        }

        $storedFiles = [];
        try {
            $model = DB::transaction(function () use ($model, $validated, $file, $canOperate, &$storedFiles) {
                $removeFile = (bool) ($validated['remove_attachment'] ?? false);
                $removeAnswerAttachment = (bool) ($validated['remove_answer_attachment'] ?? false);
                unset($validated['remove_attachment'], $validated['remove_answer_attachment']);

                // Xử lý answer_attachment file trong validated (nếu có key answer_attachment là file upload)
                $answerAttachmentFile = $validated['answer_attachment'] ?? null;
                unset($validated['answer_attachment']);

                $model->update($validated);

                // Xóa registration attachment (của đại biểu)
                if ($removeFile && $model->media_id) {
                    $this->mediaService->removeByIds($model, [$model->media_id], 'meeting-discussion-attachments');
                    $model->update(['media_id' => null]);
                }

                // Upload registration attachment mới
                if ($file) {
                    if ($model->media_id) {
                        $this->mediaService->removeByIds($model, [$model->media_id], 'meeting-discussion-attachments');
                    }
                    $media = $this->mediaService->uploadOne($model, $file, 'meeting-discussion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $model->update(['media_id' => $media->id]);
                }

                // Xóa answer attachment (operator-only)
                if ($canOperate && $removeAnswerAttachment && $model->answer_attachment_id) {
                    $this->mediaService->removeByIds($model, [$model->answer_attachment_id], 'meeting-discussion-attachments');
                    $model->update(['answer_attachment_id' => null]);
                }

                // Upload answer attachment mới (operator-only)
                if ($canOperate && $answerAttachmentFile instanceof \Illuminate\Http\UploadedFile) {
                    if ($model->answer_attachment_id) {
                        $this->mediaService->removeByIds($model, [$model->answer_attachment_id], 'meeting-discussion-attachments');
                    }
                    $media = $this->mediaService->uploadOne($model, $answerAttachmentFile, 'meeting-discussion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $model->update(['answer_attachment_id' => $media->id]);
                }

                return $model->load(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile', 'answerAttachment']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }

        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionRegistrationUpdated($model))->toOthers();

        return $model;
    }

    public function destroy(MeetingDiscussionRegistration $model): void
    {
        $this->ensureOwned($model);
        $registrationId = $model->id;
        $meetingId = $model->meeting_id;
        $model->delete();

        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionRegistrationDeleted($registrationId, $meetingId))->toOthers();
    }

    /**
     * Operator đánh dấu hoàn thành lượt phát biểu — registered -> completed.
     * Spec section 7.3: "Người điều hành đánh dấu 'Đã thảo luận' hoặc 'Đã chất vấn'."
     */
    public function complete(MeetingDiscussionRegistration $model): MeetingDiscussionRegistration
    {
        if ($model->status !== MeetingDiscussionStatusEnum::Registered->value) {
            throw ValidationException::withMessages([
                'status' => ['Đăng ký đã hoàn thành — không thể đánh dấu lại.'],
            ]);
        }

        $shouldUnhighlight = false;
        \Illuminate\Support\Facades\DB::transaction(function () use ($model, &$shouldUnhighlight) {
            $model->update([
                'status' => MeetingDiscussionStatusEnum::Completed->value,
                'completed_at' => now(),
                'highlighted_at' => null,
            ]);

            // Nếu registration này đang được highlight trên meeting -> auto unhighlight để Tab Projector + Tab Điều hành tự tắt slide người phát biểu.
            $meeting = \App\Modules\Meeting\Models\Meeting::find($model->meeting_id);
            if ($meeting && (int) $meeting->current_meeting_discussion_registration_id === (int) $model->id) {
                $meeting->update(['current_meeting_discussion_registration_id' => null]);
                $shouldUnhighlight = true;
                $model->setRelation('meeting', $meeting->fresh());
            }
        });

        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionRegistrationCompleted($model))->toOthers();

        // Bắn event unhighlight để Tab Projector/FE clear slide người phát biểu hiện tại.
        if ($shouldUnhighlight) {
            $meeting = \App\Modules\Meeting\Models\Meeting::with(['chairperson', 'operator', 'currentAgenda', 'currentDiscussionRegistration'])
                ->find($model->meeting_id);
            if ($meeting) {
                broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionHighlighted($meeting))->toOthers();
            }
        }

        return $model->load(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile']);
    }

    public function reorder(array $items): void
    {
        // Reorder mặc định cho chair/operator quản lý thứ tự gọi tên — Sprint 1 sẽ enforce
        // qua middleware meeting.role. Hiện tại tạm scope theo org để tránh cross-tenant.
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingDiscussionRegistration::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    /**
     * Throw 404 nếu đăng ký không thuộc auth user (tránh leak ID-existence).
     */
    private function ensureOwned(MeetingDiscussionRegistration $model): void
    {
        $userId = $this->resolveCurrentUserId();
        $model->loadMissing('participant.attendee');

        if ((int) ($model->participant?->attendee?->user_id ?? 0) !== (int) $userId) {
            throw new ModelNotFoundException('Không tìm thấy đăng ký.');
        }
    }

    private function nextSortOrder(int $meetingId, ?int $meetingAgendaId, string $type): int
    {
        return ((int) MeetingDiscussionRegistration::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
            ->where('meeting_agenda_id', $meetingAgendaId)
            ->where('type', $type)
            ->max('sort_order')) + 1;
    }

    /**
     * Giới hạn visibility theo role của user trong meeting.
     *   - Chair/Operator → thấy tất cả (public + private).
     *   - Participant (đại biểu) → thấy is_public=true HOẶC của chính mình (owner).
     *   - Không có role → chỉ thấy is_public=true.
     */
    private function applyVisibilityScope($query, Meeting $meeting): void
    {
        $user = $this->resolveCurrentUser();
        if (! $user) {
            $query->where('is_public', true);

            return;
        }

        if ($meeting->isChairperson($user) || $meeting->isOperator($user)) {
            return; // thấy tất cả
        }

        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if ($participant) {
            $query->where(function ($q) use ($participant) {
                $q->where('is_public', true)
                    ->orWhere('meeting_participant_id', $participant->id);
            });
        } else {
            $query->where('is_public', true);
        }
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }

    private function resolveCurrentUser(): ?\App\Modules\Core\Models\User
    {
        return auth()->user();
    }

    private function resolveCurrentUserId(): int
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new ModelNotFoundException('Cần đăng nhập để truy cập đăng ký thảo luận/chất vấn.');
        }

        return (int) $userId;
    }
}
