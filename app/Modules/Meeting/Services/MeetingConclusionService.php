<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingConclusion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingConclusionService
{
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $base = MeetingConclusion::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'published' => (clone $base)->where('status', 'published')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingConclusion::with(['creator', 'editor', 'mediaFile'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingConclusion $meetingConclusion): MeetingConclusion
    {
        return $meetingConclusion->load(['creator', 'editor', 'mediaFile']);
    }

    public function store(array $validated, $file = null): MeetingConclusion
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                $model = MeetingConclusion::create([
                    ...$validated,
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                ]);

                if ($file) {
                    $media = $this->mediaService->uploadOne($model, $file, 'meeting-conclusion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $model->update(['media_id' => $media->id]);
                }

                return $model->load(['creator', 'editor', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(MeetingConclusion $meetingConclusion, array $validated, $file = null): MeetingConclusion
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($meetingConclusion, $validated, $file, &$storedFiles) {
                $removeFile = (bool) ($validated['remove_file'] ?? false);
                unset($validated['remove_file']);
                $meetingConclusion->update($validated);

                if ($removeFile && $meetingConclusion->media_id) {
                    $this->mediaService->removeByIds($meetingConclusion, [$meetingConclusion->media_id], 'meeting-conclusion-attachments');
                    $meetingConclusion->update(['media_id' => null]);
                }

                if ($file) {
                    if ($meetingConclusion->media_id) {
                        $this->mediaService->removeByIds($meetingConclusion, [$meetingConclusion->media_id], 'meeting-conclusion-attachments');
                    }
                    $media = $this->mediaService->uploadOne($meetingConclusion, $file, 'meeting-conclusion-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $meetingConclusion->update(['media_id' => $media->id]);
                }

                return $meetingConclusion->load(['creator', 'editor', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingConclusion $meetingConclusion): void
    {
        $meetingConclusion->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingConclusion::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
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
