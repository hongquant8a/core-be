<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingDocumentAttachment;
use App\Modules\Meeting\Models\MeetingView;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MeetingDocumentService
{
    public function __construct(private MediaService $mediaService) {}

    public function publicIndex(array $filters, int $limit)
    {
        return MeetingDocument::with(['agenda', 'documentType', 'attachments.media'])
            ->where('status', 'published')
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
            $meetingDocument->status !== 'published'
            || ! $meetingDocument->is_public
            || ! $meetingDocument->meeting
            || ! $meetingDocument->meeting->is_public
            || $meetingDocument->meeting->status !== 'published'
        ) {
            throw new ModelNotFoundException('Không tìm thấy tài liệu công khai.');
        }

        $meetingDocument->increment('view_count');

        $request = request();
        MeetingView::create([
            'meeting_id' => $meetingDocument->meeting_id,
            'meeting_document_id' => $meetingDocument->id,
            'user_id' => auth()->id(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'viewed_at' => now(),
        ]);

        return $meetingDocument->fresh()->load(['agenda', 'documentType', 'attachments.media']);
    }

    public function index(array $filters, int $limit)
    {
        return MeetingDocument::with(['agenda', 'documentType', 'attachments.media', 'creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingDocument $meetingDocument): MeetingDocument
    {
        return $meetingDocument->load(['agenda', 'documentType', 'attachments.media', 'creator', 'editor']);
    }

    /**
     * @param  array<UploadedFile>  $files
     */
    public function store(array $validated, array $files = []): MeetingDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                if (! array_key_exists('sort_order', $validated)) {
                    $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id']);
                }
                $validated['organization_id'] = $this->resolveCurrentOrganizationId();
                $document = MeetingDocument::create($validated);

                $this->attachFiles($document, $files, $storedFiles);

                return $document->load(['agenda', 'documentType', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    /**
     * @param  array<UploadedFile>  $files
     * @param  array<int>  $removeAttachmentIds
     */
    public function update(MeetingDocument $meetingDocument, array $validated, array $files = [], array $removeAttachmentIds = []): MeetingDocument
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($meetingDocument, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $meetingDocument->update($validated);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($meetingDocument, $removeAttachmentIds);
                }

                $this->attachFiles($meetingDocument, $files, $storedFiles);

                return $meetingDocument->load(['agenda', 'documentType', 'attachments.media', 'creator', 'editor']);
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

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        MeetingDocument::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(MeetingDocument $meetingDocument, string $status): MeetingDocument
    {
        $meetingDocument->update(['status' => $status]);

        return $meetingDocument->load(['agenda', 'documentType', 'attachments.media', 'creator', 'editor']);
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

    /**
     * Upload từng file → tạo Media + MeetingDocumentAttachment row.
     *
     * @param  array<UploadedFile>  $files
     * @param  array<array{disk: string, path: string}>  $storedFiles  collected paths để cleanup khi rollback
     */
    private function attachFiles(MeetingDocument $document, array $files, array &$storedFiles): void
    {
        $orgId = $this->resolveCurrentOrganizationId();
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $media = $this->mediaService->uploadOne($document, $file, 'meeting-document-attachments', ['disk' => 'public']);
            $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];

            MeetingDocumentAttachment::create([
                'organization_id' => $orgId,
                'meeting_document_id' => $document->id,
                'media_id' => $media->id,
                'file_name' => $file->getClientOriginalName(),
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * @param  array<int>  $attachmentIds
     */
    private function removeAttachments(MeetingDocument $document, array $attachmentIds): void
    {
        $attachments = MeetingDocumentAttachment::query()
            ->where('meeting_document_id', $document->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        $mediaIds = $attachments->pluck('media_id')->filter()->all();
        if (! empty($mediaIds)) {
            $this->mediaService->removeByIds($document, $mediaIds, 'meeting-document-attachments');
        }
        MeetingDocumentAttachment::query()
            ->where('meeting_document_id', $document->id)
            ->whereIn('id', $attachmentIds)
            ->delete();
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
