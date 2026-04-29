<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Exports\ItemsExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemAttachment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentItemService
{
    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $base = TaskAssignmentItem::filter($filters);

        // ⚠ DESIGN DEBT — overdue đang là bucket RIÊNG (mutually exclusive với todo/in_progress/paused),
        // không phải attribute flag như đúng semantic. Sum 6 buckets = total → tiện cho FE table/donut.
        // Spec gốc + refactor đề xuất: overdue là attribute (is_overdue), subset của 3 active buckets.
        // Refactor mai sau — xem phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md "Notes về stats overdue".
        return [
            'total' => (clone $base)->count(),
            'todo' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Todo->value),
            'in_progress' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::InProgress->value),
            'paused' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Paused->value),
            'done' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Done->value)->count(),
            'overdue' => $this->countOverdue($base),
            'cancelled' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Cancelled->value)->count(),
        ];
    }

    /**
     * Count tasks với status cụ thể, LOẠI overdue (overdue tách bucket riêng).
     * ⚠ Tạm thời, không đúng semantic. Refactor sau.
     */
    private function countByStatusExcludingOverdue($base, string $status): int
    {
        return (clone $base)
            ->where('processing_status', $status)
            ->where(fn ($q) => $q
                ->where('deadline_type', '!=', TaskDeadlineTypeEnum::HasDeadline->value)
                ->orWhereNull('end_at')
                ->orWhere('end_at', '>=', now())
            )->count();
    }

    /**
     * Count tasks đang quá hạn (active status + past end_at).
     * Hiện là bucket riêng, mutually exclusive với todo/in_progress/paused. Refactor sau thành attribute.
     */
    private function countOverdue($base): int
    {
        return (clone $base)
            ->where('deadline_type', TaskDeadlineTypeEnum::HasDeadline->value)
            ->where('end_at', '<', now())
            ->whereIn('processing_status', [
                TaskProgressStatusEnum::Todo->value,
                TaskProgressStatusEnum::InProgress->value,
                TaskProgressStatusEnum::Paused->value,
            ])->count();
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentItem::with(['document', 'itemType', 'users', 'assigner', 'creator.media', 'editor.media', 'attachments.media'])
            ->withCount('reports')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentItem $item): TaskAssignmentItem
    {
        $item->load(['document', 'itemType', 'users', 'reports', 'attachments.media', 'assigner', 'creator.media', 'editor.media']);
        $item->loadCount(['reports', 'transfers', 'notes']);

        return $item;
    }

    public function store(array $validated, array $files = []): TaskAssignmentItem
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, &$storedFiles) {
                $users = $validated['users'] ?? [];

                $data = collect($validated)->except(['users', 'attachments', 'remove_attachment_ids'])->all();
                $item = TaskAssignmentItem::create($data);

                $addedUserIds = [];
                if (! empty($users)) {
                    $addedUserIds = $this->syncUsers($item, $users);
                }

                $this->uploadAttachments($item, $files, $storedFiles);

                $this->fireTaskAssignedForNewUsers($item, $addedUserIds);

                return $item->load(['document', 'itemType', 'users', 'attachments.media', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentItem $item, array $validated, array $files = [], array $removeAttachmentIds = []): TaskAssignmentItem
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($item, $validated, $files, $removeAttachmentIds, &$storedFiles) {
                $users = $validated['users'] ?? null;

                $data = collect($validated)->except(['users', 'attachments', 'remove_attachment_ids'])->all();
                $item->update($data);

                $addedUserIds = [];
                if ($users !== null) {
                    $addedUserIds = $this->syncUsers($item, $users);
                }

                if (! empty($removeAttachmentIds)) {
                    $this->removeAttachments($item, $removeAttachmentIds);
                }

                $this->uploadAttachments($item, $files, $storedFiles);

                $this->fireTaskAssignedForNewUsers($item, $addedUserIds);

                return $item->load(['document', 'itemType', 'users', 'attachments.media', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(TaskAssignmentItem $item): void
    {
        $item->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        TaskAssignmentItem::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        $data = $this->buildStatusUpdateData($status);
        TaskAssignmentItem::whereIn('id', $ids)->update($data);

        // Nếu chuyển từ done sang trạng thái khác, clear completed_at
        if ($status !== TaskProgressStatusEnum::Done->value) {
            TaskAssignmentItem::whereIn('id', $ids)
                ->whereNotNull('completed_at')
                ->where('processing_status', '!=', TaskProgressStatusEnum::Done->value)
                ->update(['completed_at' => null]);
        }
    }

    public function changeStatus(TaskAssignmentItem $item, string $status): TaskAssignmentItem
    {
        $data = $this->buildStatusUpdateData($status);

        // Reopen from done -> clear completed_at
        if ($item->processing_status === TaskProgressStatusEnum::Done->value && $status !== TaskProgressStatusEnum::Done->value) {
            $data['completed_at'] = null;
        }

        $item->update($data);

        return $item->load(['document', 'itemType', 'users', 'creator.media', 'editor.media']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new ItemsExport($filters), 'task-assignment-items.xlsx');
    }

    public function exportMonthlyReport(string $month): BinaryFileResponse
    {
        $filename = "bao-cao-giao-ban-{$month}.xlsx";

        return Excel::download(new \App\Modules\TaskAssignment\Exports\MonthlyReportExport($month), $filename);
    }

    public function updateProgress(TaskAssignmentItem $item, array $validated): TaskAssignmentItem
    {
        $item->processing_status = $validated['processing_status'] ?? $item->processing_status;
        $item->completion_percent = $validated['completion_percent'] ?? $item->completion_percent;
        $item->save();

        return $item->load(['document', 'itemType', 'users', 'creator.media', 'editor.media']);
    }

    /**
     * Manager đánh dấu công việc hoàn thành (mark-done).
     *
     * Spec §9.3.D: phải có ≥1 báo cáo manager_confirmed=true (is_locked=true).
     * Auto set: processing_status=done, completion_percent=100, completed_at=now().
     *
     * @throws \RuntimeException Khi task đã done/cancelled hoặc chưa có report locked.
     */
    public function markDone(TaskAssignmentItem $item): TaskAssignmentItem
    {
        if (in_array($item->processing_status, [
            TaskProgressStatusEnum::Done->value,
            TaskProgressStatusEnum::Cancelled->value,
        ], true)) {
            throw new \RuntimeException('Công việc đã đóng, không thể đánh dấu lại.');
        }

        $hasLockedReport = \App\Modules\TaskAssignment\Models\TaskAssignmentItemReport::where('task_assignment_item_id', $item->id)
            ->where('is_locked', true)
            ->exists();

        if (! $hasLockedReport) {
            throw new \RuntimeException('Phải có ít nhất 1 báo cáo đã được xác nhận trước khi đánh dấu hoàn thành.');
        }

        $item->update([
            'processing_status' => TaskProgressStatusEnum::Done->value,
            'completion_percent' => 100,
            'completed_at' => now(),
        ]);

        event(new \App\Services\Notification\Events\TaskConfirmed($item->fresh()));

        return $item->load(['document', 'itemType', 'users', 'creator.media', 'editor.media']);
    }

    private function buildStatusUpdateData(string $status): array
    {
        $data = ['processing_status' => $status];

        if ($status === TaskProgressStatusEnum::Done->value) {
            $data['completion_percent'] = 100;
            $data['completed_at'] = now();
        }

        return $data;
    }

    private function uploadAttachments(TaskAssignmentItem $item, array $files, array &$storedFiles): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $media = $this->mediaService->uploadOne($item, $file, 'task-item-attachments', ['disk' => 'public']);

            $storedFiles[] = [
                'disk' => $media->disk,
                'path' => $media->getPathRelativeToRoot(),
            ];

            TaskAssignmentItemAttachment::create([
                'task_assignment_item_id' => $item->id,
                'media_id' => $media->id,
                'file_name' => $file->getClientOriginalName(),
                'sort_order' => 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function removeAttachments(TaskAssignmentItem $item, array $attachmentIds): void
    {
        $attachments = TaskAssignmentItemAttachment::where('task_assignment_item_id', $item->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        foreach ($attachments as $attachment) {
            if ($attachment->media) {
                $attachment->media->delete();
            }
            $attachment->delete();
        }
    }

    /**
     * Đồng bộ pivot user — trả về list user_id MỚI thêm vào (không tính các user đã có trong list cũ).
     *
     * @return array<int> user_ids vừa được add (để fire TaskAssigned event)
     */
    private function syncUsers(TaskAssignmentItem $item, array $users): array
    {
        $previousIds = $item->users()->pluck('users.id')->all();

        $syncData = [];
        foreach ($users as $user) {
            $syncData[$user['user_id']] = [
                'department_id' => $user['department_id'],
                'department_role' => $user['department_role'],
                'assignment_role' => $user['assignment_role'],
                'assignment_status' => 'assigned',
                'assigned_at' => now(),
            ];
        }
        $item->users()->sync($syncData);

        $newIds = array_keys($syncData);

        return array_values(array_diff($newIds, $previousIds));
    }

    /**
     * Fire TaskAssigned event cho các user vừa được thêm vào item — chỉ khi document đã issued.
     *
     * @param  array<int>  $userIds
     */
    private function fireTaskAssignedForNewUsers(TaskAssignmentItem $item, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $item->loadMissing('document');
        if ($item->document?->status !== \App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum::Issued->value) {
            return;
        }

        $users = \App\Modules\Core\Models\User::whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            event(new \App\Services\Notification\Events\TaskAssigned($item, $user));
        }
    }

    private function applyDepartmentRestriction(array $filters): array
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['Quản trị', 'Super Admin', 'Admin'])) {
            $taskAssignmentUser = $user->taskAssignmentUser;
            $filters['department_id'] = $taskAssignmentUser?->task_assignment_department_id;
        }

        return $filters;
    }

    public function statsByItemType(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $itemTypes = \App\Modules\TaskAssignment\Models\TaskAssignmentItemType::where('status', 'active')->get(['id', 'name']);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        return $itemTypes->map(function ($type) use ($filters, $done, $cancelled, $hasDeadline) {
            $base = TaskAssignmentItem::where('task_assignment_item_type_id', $type->id)
                ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('task_assignment_item_user.department_id', $v)))
                ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
                ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
                ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));

            return [
                'item_type_id' => $type->id,
                'item_type_name' => $type->name,
                'total' => (clone $base)->count(),
                'todo' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Todo->value),
                'in_progress' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::InProgress->value),
                'paused' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Paused->value),
                'done' => (clone $base)->where('processing_status', $done)->count(),
                'overdue' => $this->countOverdue($base),
                'cancelled' => (clone $base)->where('processing_status', $cancelled)->count(),
            ];
        })->all();
    }

    public function statsByDepartment(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        // Iterate ALL departments (active + inactive) — task gắn với inactive dept vẫn hiển thị.
        $departments = TaskAssignmentDepartment::query()
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'name', 'code', 'status']);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;

        $applyCommonFilters = fn ($base) => $base
            ->when($filters['processing_status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['deadline_type'] ?? null, fn ($q, $v) => $q->where('deadline_type', $v))
            ->when($filters['task_assignment_item_type_id'] ?? null, fn ($q, $v) => $q->where('task_assignment_item_type_id', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $toDateEnd = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

        $rows = $departments->map(function ($dept) use ($applyCommonFilters, $done, $cancelled, $fromDate, $toDate, $toDateEnd) {
            $base = $applyCommonFilters(
                TaskAssignmentItem::whereHas('users', fn ($q) => $q->where('task_assignment_item_user.department_id', $dept->id))
            );

            // 6 buckets mutually exclusive (overdue tách riêng, không phải attribute) — sum = total.
            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'department_code' => $dept->code,
                'total' => (clone $base)->count(),
                'todo' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Todo->value),
                'in_progress' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::InProgress->value),
                'paused' => $this->countByStatusExcludingOverdue($base, TaskProgressStatusEnum::Paused->value),
                'done' => (clone $base)->where('processing_status', $done)->count(),
                'overdue' => $this->countOverdue($base),
                'cancelled' => (clone $base)->where('processing_status', $cancelled)->count(),
                'new_in_period' => ($fromDate && $toDate)
                    ? (clone $base)->whereBetween('created_at', [$fromDate, $toDateEnd])->count()
                    : null,
                'done_in_period' => ($fromDate && $toDate)
                    ? (clone $base)->where('processing_status', $done)
                        ->whereBetween('completed_at', [$fromDate, $toDateEnd])->count()
                    : null,
            ];
        })->all();

        return $rows;
    }

    public function statsByUser(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        $query = DB::table('task_assignment_item_user as tiu')
            ->join('task_assignment_items as ti', 'ti.id', '=', 'tiu.task_assignment_item_id')
            ->join('users as u', 'u.id', '=', 'tiu.user_id')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('tiu.department_id', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('ti.priority', $v))
            ->when($fromDate, fn ($q, $v) => $q->where('ti.created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->where('ti.created_at', '<=', Carbon::parse($v)->endOfDay()));

        if (! empty($filters['processing_status'])) {
            $query->where('ti.processing_status', $filters['processing_status']);
        }

        // Predicate: task quá hạn = has_deadline + end_at < now + status active (todo/in_progress/paused).
        $overdueWhen = "(ti.deadline_type = ? AND ti.end_at < NOW() AND ti.processing_status IN ('todo','in_progress','paused'))";
        // Status không overdue = chưa quá end_at hoặc no_deadline.
        $notOverdueWhen = "(ti.deadline_type != ? OR ti.end_at IS NULL OR ti.end_at >= NOW())";

        $query->groupBy('tiu.user_id', 'u.name')
            ->selectRaw('tiu.user_id, u.name as user_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'todo' AND {$notOverdueWhen} THEN 1 ELSE 0 END) as todo", [$hasDeadline])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' AND {$notOverdueWhen} THEN 1 ELSE 0 END) as in_progress", [$hasDeadline])
            ->selectRaw('SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as done', [$done])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'paused' AND {$notOverdueWhen} THEN 1 ELSE 0 END) as paused", [$hasDeadline])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as cancelled", [$cancelled])
            ->selectRaw("SUM(CASE WHEN {$overdueWhen} THEN 1 ELSE 0 END) as overdue", [$hasDeadline])
            ->selectRaw("SUM(CASE WHEN tiu.assignment_status = 'assigned' THEN 1 ELSE 0 END) as assigned_count")
            ->selectRaw("SUM(CASE WHEN tiu.assignment_status = 'done' THEN 1 ELSE 0 END) as accepted_count");

        if ($fromDate && $toDate) {
            $toDateEnd = Carbon::parse($toDate)->endOfDay();
            $query->selectRaw('SUM(CASE WHEN ti.created_at >= ? AND ti.created_at <= ? THEN 1 ELSE 0 END) as new_in_period', [$fromDate, $toDateEnd])
                ->selectRaw('SUM(CASE WHEN ti.processing_status = ? AND ti.completed_at >= ? AND ti.completed_at <= ? THEN 1 ELSE 0 END) as done_in_period', [$done, $fromDate, $toDateEnd]);
        } else {
            $query->selectRaw('NULL as new_in_period')
                ->selectRaw('NULL as done_in_period');
        }

        $results = $query->get();

        return $results->map(fn ($row) => [
            'user_id' => $row->user_id,
            'user_name' => $row->user_name,
            'total' => (int) $row->total,
            'todo' => (int) $row->todo,
            'in_progress' => (int) $row->in_progress,
            'done' => (int) $row->done,
            'paused' => (int) $row->paused,
            'cancelled' => (int) $row->cancelled,
            'overdue' => (int) $row->overdue,
            'assigned_count' => (int) $row->assigned_count,
            'accepted_count' => (int) $row->accepted_count,
            'new_in_period' => $row->new_in_period !== null ? (int) $row->new_in_period : null,
            'done_in_period' => $row->done_in_period !== null ? (int) $row->done_in_period : null,
        ])->all();
    }

    public function statsByTime(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $from = Carbon::parse($filters['from_date'])->startOfMonth();
        $to = Carbon::parse($filters['to_date'])->endOfMonth();

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $baseQuery = TaskAssignmentItem::query()
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('task_assignment_item_user.department_id', $v)))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('user_id', $v)));

        $results = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $monthKey = $cursor->format('Y-m');

            $newTasks = (clone $baseQuery)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $doneInMonth = (clone $baseQuery)
                ->where('processing_status', $done)
                ->whereBetween('completed_at', [$monthStart, $monthEnd])
                ->count();

            $overdueInMonth = (clone $baseQuery)
                ->where('deadline_type', $hasDeadline)
                ->whereBetween('end_at', [$monthStart, $monthEnd])
                ->whereNotIn('processing_status', [$done, $cancelled])
                ->count();

            $totalUpToMonth = (clone $baseQuery)
                ->where('created_at', '<=', $monthEnd)
                ->count();

            $results[] = [
                'month' => $monthKey,
                'total' => $totalUpToMonth,
                'done' => $doneInMonth,
                'overdue' => $overdueInMonth,
                'new_tasks' => $newTasks,
            ];

            $cursor->addMonth();
        }

        return $results;
    }

    public function statsByDocument(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $query = DB::table('task_assignment_items as ti')
            ->join('task_assignment_documents as td', 'td.id', '=', 'ti.task_assignment_document_id')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereExists(function ($sub) use ($v) {
                $sub->select(DB::raw(1))
                    ->from('task_assignment_item_user')
                    ->whereColumn('task_assignment_item_user.task_assignment_item_id', 'ti.id')
                    ->where('task_assignment_item_user.department_id', $v);
            }))
            ->when($filters['task_assignment_type_id'] ?? null, fn ($q, $v) => $q->where('td.task_assignment_type_id', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('td.issue_date', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('td.issue_date', '<=', $v));

        $results = $query->groupBy('td.id', 'td.name', 'td.issue_date')
            ->selectRaw('td.id as document_id, td.name as document_name, td.issue_date')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as done', [$done])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw('SUM(CASE WHEN ti.deadline_type = ? AND ti.end_at < NOW() AND ti.processing_status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue', [$hasDeadline, $done, $cancelled])
            ->orderBy('td.issue_date', 'desc')
            ->get();

        return $results->map(fn ($row) => [
            'document_id' => $row->document_id,
            'document_name' => $row->document_name,
            'issue_date' => $row->issue_date,
            'total_items' => (int) $row->total_items,
            'done' => (int) $row->done,
            'in_progress' => (int) $row->in_progress,
            'overdue' => (int) $row->overdue,
            'completion_rate' => $row->total_items > 0 ? round(((int) $row->done / (int) $row->total_items) * 100, 1) : 0,
        ])->all();
    }

    public function overdue(array $filters, int $limit)
    {
        $filters = $this->applyDepartmentRestriction($filters);

        return TaskAssignmentItem::with(['document', 'itemType', 'users'])
            ->withCount('reports')
            ->where('deadline_type', TaskDeadlineTypeEnum::HasDeadline->value)
            ->where('end_at', '<', now())
            ->whereNotIn('processing_status', [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('task_assignment_item_user.department_id', $v)))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('user_id', $v)))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->orderBy($filters['sort_by'] ?? 'end_at', $filters['sort_order'] ?? 'asc')
            ->paginate($limit);
    }

    public function upcomingDeadline(array $filters, int $limit)
    {
        $filters = $this->applyDepartmentRestriction($filters);
        $days = (int) ($filters['days'] ?? 3);

        return TaskAssignmentItem::with(['document', 'itemType', 'users'])
            ->withCount('reports')
            ->where('deadline_type', TaskDeadlineTypeEnum::HasDeadline->value)
            ->where('end_at', '>=', now())
            ->where('end_at', '<=', now()->addDays($days))
            ->whereNotIn('processing_status', [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('task_assignment_item_user.department_id', $v)))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('user_id', $v)))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->orderBy('end_at', 'asc')
            ->paginate($limit);
    }
}
