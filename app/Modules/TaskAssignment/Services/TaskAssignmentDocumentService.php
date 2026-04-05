<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Exports\DocumentsExport;
use App\Modules\TaskAssignment\Imports\DocumentsImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocumentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentDocumentService
{
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $base = TaskAssignmentDocument::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', TaskAssignmentDocumentStatusEnum::Draft->value)->count(),
            'issued' => (clone $base)->where('status', TaskAssignmentDocumentStatusEnum::Issued->value)->count(),
        ];
    }

    public function statsByTime(array $filters): array
    {
        $from = \Carbon\Carbon::parse($filters['from_date'])->startOfMonth();
        $to = \Carbon\Carbon::parse($filters['to_date'])->endOfMonth();

        $draft = TaskAssignmentDocumentStatusEnum::Draft->value;
        $issued = TaskAssignmentDocumentStatusEnum::Issued->value;

        $baseQuery = TaskAssignmentDocument::query()
            ->when($filters['task_assignment_type_id'] ?? null, fn ($q, $v) => $q->where('task_assignment_type_id', $v));

        $results = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $base = (clone $baseQuery)->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

            $results[] = [
                'month' => $cursor->format('Y-m'),
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', $draft)->count(),
                'issued' => (clone $base)->where('status', $issued)->count(),
            ];

            $cursor->addMonth();
        }

        return $results;
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentDocument::with(['type', 'creator', 'editor'])
            ->withCount('items')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentDocument $document): TaskAssignmentDocument
    {
        return $document->load(['type', 'items', 'attachments.media', 'creator', 'editor']);
    }

    public function store(array $validated, array $files = []): TaskAssignmentDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                $data = collect($validated)->except(['files', 'remove_attachment_ids'])->all();
                $document = TaskAssignmentDocument::create($data);

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        continue;
                    }

                    $media = $this->mediaService->uploadOne($document, $file, 'task-document-attachments', ['disk' => 'public']);

                    $storedFiles[] = [
                        'disk' => $media->disk,
                        'path' => $media->getPathRelativeToRoot(),
                    ];

                    TaskAssignmentDocumentAttachment::create([
                        'task_assignment_document_id' => $document->id,
                        'media_id' => $media->id,
                        'file_name' => $file->getClientOriginalName(),
                        'sort_order' => 0,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }

                return $document->load(['type', 'items', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentDocument $document, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentDocument
    {
        if ($document->status === TaskAssignmentDocumentStatusEnum::Issued->value) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Không thể chỉnh sửa văn bản đã ban hành. Vui lòng chuyển về trạng thái nháp trước.'],
            ]);
        }

        $storedFiles = [];

        try {
            return DB::transaction(function () use ($document, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['files', 'remove_attachment_ids'])->all();
                $document->update($data);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($document, $removeAttachmentIds);
                }

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        continue;
                    }

                    $media = $this->mediaService->uploadOne($document, $file, 'task-document-attachments', ['disk' => 'public']);

                    $storedFiles[] = [
                        'disk' => $media->disk,
                        'path' => $media->getPathRelativeToRoot(),
                    ];

                    TaskAssignmentDocumentAttachment::create([
                        'task_assignment_document_id' => $document->id,
                        'media_id' => $media->id,
                        'file_name' => $file->getClientOriginalName(),
                        'sort_order' => 0,
                        'created_by' => auth()->id(),
                        'updated_by' => auth()->id(),
                    ]);
                }

                return $document->load(['type', 'items', 'attachments.media', 'creator', 'editor']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentDocument $document): void
    {
        $document->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        TaskAssignmentDocument::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        DB::transaction(function () use ($ids, $status) {
            $documents = TaskAssignmentDocument::whereIn('id', $ids)->get();

            foreach ($documents as $document) {
                $this->changeStatus($document, $status);
            }
        });
    }

    public function changeStatus(TaskAssignmentDocument $document, string $status): TaskAssignmentDocument
    {
        if ($status === TaskAssignmentDocumentStatusEnum::Issued->value) {
            if ($document->items()->count() === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => ['Văn bản phải có ít nhất một công việc trước khi ban hành.'],
                ]);
            }

            $invalidItems = $document->items()
                ->where('deadline_type', 'has_deadline')
                ->whereNull('end_at')
                ->exists();

            if ($invalidItems) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => ['Tất cả công việc có thời hạn phải có ngày kết thúc trước khi ban hành.'],
                ]);
            }

            $document->update(['status' => $status, 'issued_at' => now()]);
        } else {
            $document->update(['status' => $status, 'issued_at' => null]);
        }

        return $document->load(['type', 'attachments.media', 'creator', 'editor']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DocumentsExport($filters), 'task-assignment-documents.xlsx');
    }

    public function import($file): void
    {
        Excel::import(new DocumentsImport, $file);
    }

    private function removeAttachments(TaskAssignmentDocument $document, array $attachmentIds): void
    {
        $attachments = TaskAssignmentDocumentAttachment::where('task_assignment_document_id', $document->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        foreach ($attachments as $attachment) {
            if ($attachment->media) {
                $attachment->media->delete();
            }
            $attachment->delete();
        }
    }
}
