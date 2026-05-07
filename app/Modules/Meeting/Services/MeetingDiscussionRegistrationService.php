<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Enums\MeetingDiscussionTypeEnum;
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
 *   - Chủ trì gọi đại biểu phát biểu (status `registered -> called`) — Sprint 1 endpoint /start.
 *   - Điều hành đánh dấu hoàn thành (status `called -> completed`) — Sprint 1 endpoint /complete.
 *   - Index/show/stats: ai xem được meeting đều xem được (FE quyết định visibility).
 */
class MeetingDiscussionRegistrationService
{
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $base = MeetingDiscussionRegistration::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'registered' => (clone $base)->where('status', MeetingDiscussionStatusEnum::Registered->value)->count(),
            'called' => (clone $base)->where('status', MeetingDiscussionStatusEnum::Called->value)->count(),
            'completed' => (clone $base)->where('status', MeetingDiscussionStatusEnum::Completed->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingDiscussionRegistration::with(['participant', 'agenda', 'mediaFile'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingDiscussionRegistration $meetingDiscussionRegistration): MeetingDiscussionRegistration
    {
        return $meetingDiscussionRegistration->load(['participant', 'agenda', 'mediaFile']);
    }

    public function store(array $validated, $file = null): MeetingDiscussionRegistration
    {
        $userId = $this->resolveCurrentUserId();
        $meetingId = (int) $validated['meeting_id'];
        $type = $validated['type'];
        $agendaId = $validated['meeting_agenda_id'] ?? null;

        // Auto-derive meeting_participant_id từ auth user — tránh đại biểu A đăng ký hộ B.
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meetingId)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
        }

        // Spec line 276: BE kiểm tra cờ cho phép theo agenda.
        // Type=discussion → agenda.allow_discussion_registration phải true.
        // Type=question → agenda.allow_question_registration phải true.
        // Nếu không gắn agenda (agendaId=null) thì không check (đăng ký chung cuộc họp).
        if ($agendaId !== null) {
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
        }

        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, $participant, $meetingId, $type, $agendaId, &$storedFiles) {

                $payload = [
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                    'meeting_id' => $meetingId,
                    'meeting_participant_id' => $participant->id,
                    'meeting_agenda_id' => $agendaId,
                    'type' => $type,
                    'content' => $validated['content'],
                    'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($meetingId, $agendaId, $type),
                    'status' => $validated['status'] ?? MeetingDiscussionStatusEnum::Registered->value,
                ];

                $model = MeetingDiscussionRegistration::create($payload);

                if ($file) {
                    $media = $this->mediaService->uploadOne($model, $file, 'meeting-discussion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $model->update(['media_id' => $media->id]);
                }

                return $model->load(['participant', 'agenda', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(MeetingDiscussionRegistration $model, array $validated, $file = null): MeetingDiscussionRegistration
    {
        $this->ensureOwned($model);

        $storedFiles = [];
        try {
            return DB::transaction(function () use ($model, $validated, $file, &$storedFiles) {
                $removeFile = (bool) ($validated['remove_attachment'] ?? false);
                unset($validated['remove_attachment']);
                $model->update($validated);

                if ($removeFile && $model->media_id) {
                    $this->mediaService->removeByIds($model, [$model->media_id], 'meeting-discussion-attachments');
                    $model->update(['media_id' => null]);
                }

                if ($file) {
                    if ($model->media_id) {
                        $this->mediaService->removeByIds($model, [$model->media_id], 'meeting-discussion-attachments');
                    }
                    $media = $this->mediaService->uploadOne($model, $file, 'meeting-discussion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $model->update(['media_id' => $media->id]);
                }

                return $model->load(['participant', 'agenda', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingDiscussionRegistration $model): void
    {
        $this->ensureOwned($model);
        $model->delete();
    }

    /**
     * Chủ trì gọi đại biểu phát biểu — registered -> called.
     * Spec section 7.3: "Chủ trì có thể gọi đại biểu thảo luận/chất vấn."
     */
    public function start(MeetingDiscussionRegistration $model): MeetingDiscussionRegistration
    {
        if ($model->status !== MeetingDiscussionStatusEnum::Registered->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Đăng ký không ở trạng thái chờ — không thể gọi phát biểu.'],
            ]);
        }

        $model->update([
            'status' => MeetingDiscussionStatusEnum::Called->value,
            'called_at' => now(),
        ]);

        return $model->load(['participant', 'agenda', 'mediaFile']);
    }

    /**
     * Điều hành đánh dấu hoàn thành lượt phát biểu — called -> completed.
     * Spec section 7.3: "Người điều hành đánh dấu 'Đã thảo luận' hoặc 'Đã chất vấn'."
     */
    public function complete(MeetingDiscussionRegistration $model): MeetingDiscussionRegistration
    {
        if ($model->status !== MeetingDiscussionStatusEnum::Called->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Đăng ký chưa được gọi — không thể đánh dấu hoàn thành.'],
            ]);
        }

        $model->update([
            'status' => MeetingDiscussionStatusEnum::Completed->value,
            'completed_at' => now(),
        ]);

        return $model->load(['participant', 'agenda', 'mediaFile']);
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

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
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
