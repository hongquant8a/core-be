<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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

        // Auto-derive meeting_participant_id từ auth user — tránh đại biểu A đăng ký hộ B.
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $meetingId)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
        }

        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, $participant, $meetingId, &$storedFiles) {
                $type = $validated['type'];
                $agendaId = $validated['meeting_agenda_id'] ?? null;

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
                $removeFile = (bool) ($validated['remove_file'] ?? false);
                unset($validated['remove_file']);
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
