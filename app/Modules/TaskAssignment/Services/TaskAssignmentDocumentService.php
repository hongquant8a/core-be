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
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
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
        // Bỏ `ai_source_content` khỏi danh sách: nguyên văn dán vào ô AI có thể
        // tới 150KB mỗi bản ghi, kéo cả trang về chỉ để hiện tên và trạng thái là
        // phí băng thông lẫn bộ nhớ. Chi tiết (show) vẫn lấy đủ cột.
        // select() phải đứng TRƯỚC withCount: withCount nối subquery vào danh
        // sách cột đang có, gọi sau sẽ xoá mất mấy cột count đó.
        return TaskAssignmentDocument::select(TaskAssignmentDocument::LIST_COLUMNS)
            ->with(['type', 'creator.media', 'editor.media'])
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

                // Chỉ báo khi văn bản ĐÃ ban hành: sửa bản nháp là việc soạn thảo
                // bình thường, không ai cần biết. Đã ban hành mà sửa thì người
                // đang thực hiện công việc bên trong cần xem lại nội dung chỉ đạo.
                if ($document->status === TaskAssignmentDocumentStatusEnum::Issued->value) {
                    event(new \App\Services\Notification\Events\DocumentUpdated($document->fresh()));
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
        $this->softDeleteMany([$document->id]);
    }

    public function bulkDestroy(array $ids): void
    {
        $this->softDeleteMany($ids);
    }

    /**
     * Xoá mềm văn bản kèm công việc và báo cáo bên trong.
     *
     * Khoá ngoại cascade chỉ kích hoạt khi xoá CỨNG, nên phải làm tường minh ở
     * đây. Cả ba tầng dùng CHUNG một mốc `deleted_at`: lúc khôi phục chỉ lấy lại
     * đúng những bản ghi bị xoá cùng lần đó, không vô tình moi lên công việc đã
     * bị xoá lẻ từ trước vì lý do khác.
     *
     * Ghi từng dòng bằng Eloquent chứ không mass update để observer chạy —
     * `TaskAssignmentItemObserver::deleted` huỷ lịch nhắc đang chờ, không thì
     * người thực hiện vẫn bị nhắc về việc đã xoá.
     *
     * @param  array<int>  $ids
     */
    private function softDeleteMany(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        DB::transaction(function () use ($ids) {
            $documents = TaskAssignmentDocument::whereIn('id', $ids)->get();
            if ($documents->isEmpty()) {
                return;
            }

            $items = TaskAssignmentItem::withoutGlobalScope('issuedDocument')
                ->whereIn('task_assignment_document_id', $documents->pluck('id'))
                ->get();

            $reports = TaskAssignmentItemReport::whereIn('task_assignment_item_id', $items->pluck('id'))->get();

            $deletedAt = now();

            // Xoá chuẩn để sự kiện `deleted` chạy (observer huỷ lịch nhắc), rồi
            // ép `deleted_at` về mốc chung của cả lô bằng `saveQuietly` để không
            // phát lại sự kiện. Mốc chung là thứ cho phép khôi phục đúng bộ.
            $stamp = function ($model) use ($deletedAt) {
                $model->delete();
                $model->deleted_at = $deletedAt;
                $model->saveQuietly();
            };

            $reports->each($stamp);
            $items->each($stamp);
            $documents->each($stamp);
        });
    }

    /**
     * Khôi phục văn bản kèm đúng bộ công việc và báo cáo đã xoá cùng lần.
     *
     * Lọc theo `deleted_at` bằng mốc của văn bản: công việc bị xoá lẻ trước đó
     * (mốc khác) phải ở nguyên trong thùng rác của nó, không theo lên.
     */
    public function restore(TaskAssignmentDocument $document): TaskAssignmentDocument
    {
        DB::transaction(function () use ($document) {
            $deletedAt = $document->deleted_at;

            $items = TaskAssignmentItem::onlyTrashed()
                ->withoutGlobalScope('issuedDocument')
                ->where('task_assignment_document_id', $document->id)
                ->when($deletedAt, fn ($q) => $q->where('deleted_at', $deletedAt))
                ->get();

            TaskAssignmentItemReport::onlyTrashed()
                ->whereIn('task_assignment_item_id', $items->pluck('id'))
                ->when($deletedAt, fn ($q) => $q->where('deleted_at', $deletedAt))
                ->get()
                ->each->restore();

            // `restore()` phát sự kiện `restored` — observer dựng lại lịch nhắc.
            $items->each->restore();

            $document->restore();
        });

        return $document->load(['type', 'creator.media', 'editor.media']);
    }

    /** Thùng rác văn bản — dùng lại đúng bộ lọc và phạm vi của `index`. */
    public function trash(array $filters, int $limit)
    {
        $query = TaskAssignmentDocument::onlyTrashed()
            ->with(['type', 'creator.media', 'editor.media'])
            ->withCount(['items' => fn ($q) => $q->onlyTrashed()->withoutGlobalScope('issuedDocument')])
            ->orderByDesc('deleted_at');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return $query->paginate($limit);
    }

    // Đã bỏ `cleanupOrphanNotifications()`: nó xoá vĩnh viễn Notification và
    // NotificationDelivery trỏ tới công việc, để tránh thông báo mồ côi khi công
    // việc bị xoá CỨNG. Nay xoá là xoá mềm — bản ghi vẫn còn, khôi phục được, nên
    // xoá vĩnh viễn thông báo cho một thao tác đảo ngược được là sai. Bấm vào
    // thông báo của công việc đang trong thùng rác sẽ nhận 404 cho tới khi khôi
    // phục; đó là cái giá đúng so với mất hẳn thông báo.

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
        $notificationIds = Notification::where('notifiable_type', (new TaskAssignmentItem)->getMorphClass())
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
