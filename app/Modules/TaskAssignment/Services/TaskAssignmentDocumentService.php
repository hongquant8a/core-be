<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Exports\DocumentsExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocumentAttachment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentDocumentService
{
    public function __construct(
        private MediaService $mediaService,
        private ReminderScheduler $reminderScheduler,
    ) {}

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
        return TaskAssignmentDocument::with(['type', 'creator.media', 'editor.media'])
            ->withCount([
                'items',
                'items as completed_items_count' => function ($query) {
                    $query->where('processing_status', \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value);
                },
            ])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentDocument $document): TaskAssignmentDocument
    {
        return $document->load(['type', 'items.attachments.media', 'attachments.media', 'creator.media', 'editor.media'])
            ->loadCount([
                'items',
                'items as completed_items_count' => function ($query) {
                    $query->where('processing_status', \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Done->value);
                },
            ]);
    }

    public function store(array $validated, array $files = []): TaskAssignmentDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                $data = collect($validated)->except(['attachments', 'remove_attachment_ids'])->all();
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

                return $document->load(['type', 'items', 'attachments.media', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentDocument $document, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($document, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['attachments', 'remove_attachment_ids'])->all();
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

                return $document->load(['type', 'items', 'attachments.media', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $this->cleanupOrphanNotifications($document->items()->withoutGlobalScope('issuedDocument')->pluck('id')->all());
            $document->delete();
        });
    }

    public function bulkDestroy(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $itemIds = TaskAssignmentItem::withoutGlobalScope('issuedDocument')->whereIn('task_assignment_document_id', $ids)->pluck('id')->all();
            $this->cleanupOrphanNotifications($itemIds);
            TaskAssignmentDocument::whereIn('id', $ids)->delete();
        });
    }

    /**
     * Xóa Notification + NotificationDelivery liên quan đến items trước khi document bị xóa.
     * FK của notifications là polymorphic (notifiable_type + notifiable_id) → không cascade
     * theo FK schema. Nếu không xóa, in-app notification còn lại trỏ đến item không tồn tại
     * (404 khi user click), worker xử lý job sẽ mark delivery 'failed' với message "Notifiable
     * no longer exists".
     *
     * @param  array<int>  $itemIds
     */
    private function cleanupOrphanNotifications(array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }

        $notificationIds = Notification::where('notifiable_type', (new TaskAssignmentItem())->getMorphClass())
            ->whereIn('notifiable_id', $itemIds)
            ->pluck('id')
            ->all();
        if (empty($notificationIds)) {
            return;
        }

        // Delivery FK has cascadeOnDelete on notification_id → tự xóa cùng Notification
        Notification::whereIn('id', $notificationIds)->delete();
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
        $previousStatus = $document->status;
        $issued = TaskAssignmentDocumentStatusEnum::Issued->value;
        $draft = TaskAssignmentDocumentStatusEnum::Draft->value;

        if ($status === $issued) {
            if ($document->items()->withoutGlobalScope('issuedDocument')->count() === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => ['Văn bản phải có ít nhất một công việc trước khi ban hành.'],
                ]);
            }

            $invalidItems = $document->items()
                ->withoutGlobalScope('issuedDocument')
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

        // Revert issued → draft: dọn reminder pending + cancel notifications/deliveries chưa gửi
        if ($previousStatus === $issued && $status === $draft) {
            $this->cancelPendingNotificationsForDocument($document);
        }

        // Re-issue draft → issued: re-create reminder cho items đang còn (đã bị cancel ở revert)
        if ($status === $issued && $previousStatus !== $status) {
            foreach ($document->items as $item) {
                $this->reminderScheduler->scheduleFor($item);
            }
            event(new DocumentIssued($document->fresh()));
        }

        return $document->load(['type', 'attachments.media', 'creator.media', 'editor.media']);
    }

    /**
     * Khi revert document `issued → draft`: cancel mọi reminder pending + Notification/Delivery
     * pending của items thuộc document. Mục đích: không gửi nhắc lệch context, không gửi
     * mail/SMS ban hành đang queue chưa xử lý.
     */
    private function cancelPendingNotificationsForDocument(TaskAssignmentDocument $document): void
    {
        $itemIds = $document->items()->withoutGlobalScope('issuedDocument')->pluck('id')->all();
        if (empty($itemIds)) {
            return;
        }

        // 1. Cancel reminders pending
        foreach ($document->items as $item) {
            $this->reminderScheduler->cancelPending($item);
        }

        // 2. Cancel Notification + Delivery pending của items này
        $notificationIds = Notification::where('notifiable_type', (new TaskAssignmentItem())->getMorphClass())
            ->whereIn('notifiable_id', $itemIds)
            ->pluck('id')
            ->all();
        if (empty($notificationIds)) {
            return;
        }
        NotificationDelivery::whereIn('notification_id', $notificationIds)
            ->where('status', 'pending')
            ->update(['status' => 'skipped', 'error_message' => 'Document reverted to draft']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DocumentsExport($filters), ExportFilename::make('van-ban-giao-viec'));
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
