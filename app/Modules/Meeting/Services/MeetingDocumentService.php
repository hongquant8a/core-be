<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingDocumentService
{
    public function __construct(private MediaService $mediaService) {}

    public function publicIndex(array $filters, int $limit)
    {
        return MeetingDocument::with(['agenda', 'documentType', 'mediaFile'])
            ->where('is_public', true)
            ->whereHas('meeting', function ($query) {
                $query->where('is_public', true)
                    ->where('status', 'published');
            })
            ->when($filters['meeting_id'] ?? null, fn ($q, $meetingId) => $q->where('meeting_id', $meetingId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($limit);
    }

    public function publicShow(MeetingDocument $meetingDocument): MeetingDocument
    {
        if (
            ! $meetingDocument->is_public
            || ! $meetingDocument->meeting
            || ! $meetingDocument->meeting->is_public
            || $meetingDocument->meeting->status !== 'published'
        ) {
            throw new ModelNotFoundException('Không tìm thấy tài liệu công khai.');
        }

        return $meetingDocument->load(['agenda', 'documentType', 'mediaFile']);
    }

    /**
     * Track + resolve download URL cho tài liệu (cả public và authenticated dùng chung logic).
     *
     * @return array{url: string, file_name: ?string}
     */
    public function trackDownload(MeetingDocument $meetingDocument): array
    {
        $meetingDocument->loadMissing('mediaFile');
        if (! $meetingDocument->mediaFile) {
            throw new ModelNotFoundException('Tài liệu không có tệp đính kèm.');
        }

        $request = request();

        DB::transaction(function () use ($meetingDocument, $request) {
            $meetingDocument->increment('download_count');
            MeetingView::create([
                'meeting_id' => $meetingDocument->meeting_id,
                'meeting_document_id' => $meetingDocument->id,
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'viewed_at' => now(),
            ]);
        });

        $media = $meetingDocument->mediaFile;

        return [
            'url' => '/storage/'.$media->id.'/'.$media->file_name,
            'file_name' => $media->file_name,
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingDocument::with(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingDocument $meetingDocument): MeetingDocument
    {
        return $meetingDocument->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
    }

    public function store(array $validated, $file = null): MeetingDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                if (! array_key_exists('sort_order', $validated)) {
                    $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id']);
                }
                $validated['organization_id'] = $this->resolveCurrentOrganizationId();
                $document = MeetingDocument::create($validated);

                if ($file) {
                    $media = $this->mediaService->uploadOne($document, $file, 'meeting-document-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $document->update(['media_id' => $media->id]);
                }

                return $document->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(MeetingDocument $meetingDocument, array $validated, $file = null): MeetingDocument
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($meetingDocument, $validated, $file, &$storedFiles) {
                $removeFile = (bool) ($validated['remove_file'] ?? false);
                unset($validated['remove_file']);
                $meetingDocument->update($validated);

                if ($removeFile && $meetingDocument->media_id) {
                    $this->mediaService->removeByIds($meetingDocument, [$meetingDocument->media_id], 'meeting-document-attachments');
                    $meetingDocument->update(['media_id' => null]);
                }

                if ($file) {
                    if ($meetingDocument->media_id) {
                        $this->mediaService->removeByIds($meetingDocument, [$meetingDocument->media_id], 'meeting-document-attachments');
                    }
                    $media = $this->mediaService->uploadOne($meetingDocument, $file, 'meeting-document-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $meetingDocument->update(['media_id' => $media->id]);
                }

                return $meetingDocument->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingDocument $meetingDocument): void
    {
        $meetingDocument->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingDocument::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingDocument::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $meetingId): int
    {
        return ((int) MeetingDocument::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
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
