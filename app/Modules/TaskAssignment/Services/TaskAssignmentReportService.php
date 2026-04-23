<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReportAttachment;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Events\TaskConfirmed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TaskAssignmentReportService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(int $itemId, int $limit)
    {
        return TaskAssignmentItemReport::where('task_assignment_item_id', $itemId)
            ->with(['reporter', 'managerConfirmer', 'locker', 'attachments.media', 'item:id,end_at,task_assignment_document_id'])
            ->paginate($limit);
    }

    public function store(TaskAssignmentItem $item, array $validated, array $files = []): TaskAssignmentItemReport
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($item, $validated, $files, &$storedFiles) {
                $data = collect($validated)->except(['files', 'task_assignment_item_id'])->all();
                $data['task_assignment_item_id'] = $item->id;
                $report = TaskAssignmentItemReport::create($data);

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        continue;
                    }

                    $media = $this->mediaService->uploadOne($report, $file, 'task-report-attachments', ['disk' => 'public']);

                    $storedFiles[] = [
                        'disk' => $media->disk,
                        'path' => $media->getPathRelativeToRoot(),
                    ];

                    TaskAssignmentItemReportAttachment::create([
                        'task_assignment_item_report_id' => $report->id,
                        'media_id' => $media->id,
                        'file_name' => $file->getClientOriginalName(),
                        'sort_order' => 0,
                    ]);
                }

                $loaded = $report->load(['reporter', 'managerConfirmer', 'locker', 'attachments.media', 'item:id,end_at,task_assignment_document_id']);

                // Auto-mark reporter's assignment as done
                $reporterId = $report->reporter_user_id;
                if ($reporterId) {
                    DB::table('task_assignment_item_user')
                        ->where('task_assignment_item_id', $item->id)
                        ->where('user_id', $reporterId)
                        ->update([
                            'assignment_status' => \App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum::Done->value,
                            'completed_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                event(new TaskCompleted($report->item));

                return $loaded;
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function confirm(TaskAssignmentItemReport $report, ?string $note): TaskAssignmentItemReport
    {
        if ($report->is_locked) {
            throw new \RuntimeException('Báo cáo đã khóa, không thể xác nhận lại.');
        }

        $uid = auth()->id();

        $report->update([
            'manager_confirmed' => true,
            'manager_confirmed_by' => $uid,
            'manager_confirmed_at' => now(),
            'manager_confirm_note' => $note,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $uid,
        ]);

        $fresh = $report->fresh(['reporter', 'managerConfirmer', 'locker', 'attachments.media', 'item:id,end_at,task_assignment_document_id']);

        event(new TaskConfirmed($fresh->item));

        return $fresh;
    }

    public function update(TaskAssignmentItemReport $report, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentItemReport
    {
        if ($report->is_locked) {
            throw new \RuntimeException('Báo cáo đã khóa, không thể sửa.');
        }

        $storedFiles = [];

        try {
            return DB::transaction(function () use ($report, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['files', 'remove_attachment_ids'])->all();
                $report->update($data);

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($report, $removeAttachmentIds);
                }

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        continue;
                    }

                    $media = $this->mediaService->uploadOne($report, $file, 'task-report-attachments', ['disk' => 'public']);

                    $storedFiles[] = [
                        'disk' => $media->disk,
                        'path' => $media->getPathRelativeToRoot(),
                    ];

                    TaskAssignmentItemReportAttachment::create([
                        'task_assignment_item_report_id' => $report->id,
                        'media_id' => $media->id,
                        'file_name' => $file->getClientOriginalName(),
                        'sort_order' => 0,
                    ]);
                }

                return $report->load(['reporter', 'managerConfirmer', 'locker', 'attachments.media', 'item:id,end_at,task_assignment_document_id']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentItemReport $report): void
    {
        if ($report->is_locked) {
            throw new \RuntimeException('Báo cáo đã khóa, không thể xóa.');
        }

        $attachments = $report->attachments()->with('media')->get();

        foreach ($attachments as $attachment) {
            if ($attachment->media) {
                $attachment->media->delete();
            }
            $attachment->delete();
        }

        $report->delete();
    }

    private function removeAttachments(TaskAssignmentItemReport $report, array $attachmentIds): void
    {
        $attachments = TaskAssignmentItemReportAttachment::where('task_assignment_item_report_id', $report->id)
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
