<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReportAttachment;
use App\Services\Notification\Events\TaskCompleted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TaskAssignmentReportService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(int $itemId, int $limit)
    {
        return TaskAssignmentItemReport::where('task_assignment_item_id', $itemId)
            ->with(['reporter', 'assignee', 'creator', 'editor', 'attachments.media', 'item:id,end_at,task_assignment_document_id'])
            ->paginate($limit);
    }

    public function store(TaskAssignmentItem $item, array $validated, array $files = []): TaskAssignmentItemReport
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($item, $validated, $files, &$storedFiles) {
                $reportData = collect($validated)->except(['attachments', 'task_assignment_item_id'])->all();
                $reportData['task_assignment_item_id'] = $item->id;
                $report = TaskAssignmentItemReport::create($reportData);

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

                // Submit báo cáo kèm completion_percent → cập nhật tiến độ item.
                if (array_key_exists('completion_percent', $validated)) {
                    $percent = (int) $validated['completion_percent'];
                    $item->completion_percent = $percent;

                    if ($percent >= 100) {
                        $item->processing_status = TaskProgressStatusEnum::PendingApproval->value;
                        $item->reported_at = now();
                        $item->reported_by = auth()->id();
                        $item->rejection_reason = null;
                    } elseif ($percent > 0 && $item->processing_status === TaskProgressStatusEnum::Todo->value) {
                        $item->processing_status = TaskProgressStatusEnum::InProgress->value;
                    }

                    $item->save();
                }

                $loaded = $report->load(['reporter', 'assignee', 'creator', 'editor', 'attachments.media', 'item:id,end_at,task_assignment_document_id']);

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

                return $loaded;
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentItemReport $report, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentItemReport
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($report, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $data = collect($validated)->except(['attachments', 'remove_attachment_ids'])->all();
                $report->update($data);

                // Cập nhật tiến độ item nếu có completion_percent.
                if (array_key_exists('completion_percent', $validated)) {
                    $item = $report->item;
                    $percent = (int) $validated['completion_percent'];
                    $item->completion_percent = $percent;

                    if ($percent >= 100) {
                        $item->processing_status = TaskProgressStatusEnum::PendingApproval->value;
                        $item->reported_at = now();
                        $item->reported_by = auth()->id();
                        $item->rejection_reason = null;
                    } elseif ($percent > 0 && $item->processing_status === TaskProgressStatusEnum::Todo->value) {
                        $item->processing_status = TaskProgressStatusEnum::InProgress->value;
                    }

                    $item->save();
                }

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

                return $report->load(['reporter', 'creator', 'editor', 'attachments.media', 'item:id,end_at,task_assignment_document_id']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentItemReport $report): void
    {

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
