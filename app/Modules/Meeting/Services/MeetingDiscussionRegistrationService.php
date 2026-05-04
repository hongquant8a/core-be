<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                if (! array_key_exists('sort_order', $validated)) {
                    $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id'], $validated['meeting_agenda_id'] ?? null, $validated['type']);
                }
                $model = MeetingDiscussionRegistration::create([
                    ...$validated,
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                    'status' => $validated['status'] ?? MeetingDiscussionStatusEnum::Registered->value,
                ]);

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
        $model->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingDiscussionRegistration::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingDiscussionRegistration::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
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
}
