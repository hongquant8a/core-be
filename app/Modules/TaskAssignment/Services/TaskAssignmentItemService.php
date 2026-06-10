<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Exports\ItemsExport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemAttachment;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
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

        return [
            'total' => (clone $base)->count(),
            'todo' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
            'in_progress' => (clone $base)->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
            'paused' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
            'done' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Done->value)->count(),
            'cancelled' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Cancelled->value)->count(),
            'timing_stats' => $this->getTimingStats($base),
        ];
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

    private function getTimingStats($base): array
    {
        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        // Upcoming: active statuses, and (no deadline or end_at >= now)
        $upcoming = (clone $base)->whereNotIn('processing_status', [$done, $cancelled])
            ->where(fn ($sub) => $sub->where('deadline_type', '!=', $hasDeadline)
                ->orWhereNull('end_at')
                ->orWhere('end_at', '>=', now())
            )->count();

        // Overdue: active statuses, has deadline, end_at < now
        $overdue = $this->countOverdue($base); // Reuse existing overdue logic

        // Late: done status, has deadline, completed_at > end_at
        $late = (clone $base)->where('processing_status', $done)
            ->where('deadline_type', $hasDeadline)
            ->whereColumn('completed_at', '>', 'end_at')->count();

        // Early: done status, has deadline, DATE(completed_at) < DATE(end_at)
        $early = (clone $base)->where('processing_status', $done)
            ->where('deadline_type', $hasDeadline)
            ->whereRaw('DATE(completed_at) < DATE(end_at)')->count();

        // On time: done status, and (no deadline or DATE(completed_at) = DATE(end_at))
        $onTime = (clone $base)->where('processing_status', $done)
            ->where(fn ($sub) => $sub->where('deadline_type', '!=', $hasDeadline)
                ->orWhereNull('end_at')
                ->orWhereRaw('DATE(completed_at) = DATE(end_at)')
            )->count();

        // Cancelled: cancelled status
        $cancelledCount = (clone $base)->where('processing_status', $cancelled)->count();

        return [
            'upcoming' => $upcoming,
            'overdue' => $overdue,
            'late' => $late,
            'early' => $early,
            'on_time' => $onTime,
            'cancelled' => $cancelledCount,
        ];
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentItem::with(['document', 'itemType', 'users', 'assigner', 'creator.media', 'editor.media', 'attachments.media', 'reminders'])
            ->withCount('reports')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentItem $item): TaskAssignmentItem
    {
        $item->load(['document', 'itemType', 'users', 'reports', 'attachments.media', 'assigner', 'creator.media', 'editor.media', 'reminders']);
        $item->loadCount(['reports', 'transfers', 'notes']);

        return $item;
    }

    public function store(array $validated, array $files = [], array $reminders = []): TaskAssignmentItem
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $files, $reminders, &$storedFiles) {
                $users = $validated['users'] ?? [];

                $data = collect($validated)->except(['users', 'attachments', 'remove_attachment_ids'])->all();
                $item = TaskAssignmentItem::create($data);

                $addedUserIds = [];
                if (! empty($users)) {
                    $addedUserIds = $this->syncUsers($item, $users);
                }

                $this->uploadAttachments($item, $files, $storedFiles);

                $this->fireTaskAssignedForNewUsers($item, $addedUserIds);

                if (! empty($reminders)) {
                    $this->syncReminders($item, $reminders);
                }

                return $item->load(['document', 'itemType', 'users', 'attachments.media', 'creator.media', 'editor.media', 'reminders']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(TaskAssignmentItem $item, array $validated, array $files = [], array $removeAttachmentIds = [], ?array $reminders = null): TaskAssignmentItem
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($item, $validated, $files, $removeAttachmentIds, $reminders, &$storedFiles) {
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

                if (! empty($reminders)) {
                    $this->syncReminders($item, $reminders);
                }

                return $item->load(['document', 'itemType', 'users', 'attachments.media', 'creator.media', 'editor.media', 'reminders']);
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
        TaskAssignmentItem::where('organization_id', getPermissionsTeamId())->whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        TaskAssignmentItem::where('organization_id', getPermissionsTeamId())->whereIn('id', $ids)->update($this->buildStatusUpdateData($status));
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
        return Excel::download(new ItemsExport($filters), ExportFilename::make('cong-viec-giao'));
    }

    public function exportMonthlyReport(string $month): BinaryFileResponse
    {
        return Excel::download(
            new \App\Modules\TaskAssignment\Exports\MonthlyReportExport($month),
            ExportFilename::make("bao-cao-giao-ban-{$month}"),
        );
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
     * Auto set: processing_status=done, completion_percent=100, completed_at=now().
     *
     * @throws \RuntimeException Khi task đã done/cancelled.
     */
    public function markDone(TaskAssignmentItem $item): TaskAssignmentItem
    {
        if (in_array($item->processing_status, [
            TaskProgressStatusEnum::Done->value,
            TaskProgressStatusEnum::Cancelled->value,
        ], true)) {
            throw new \RuntimeException('Công việc đã đóng, không thể đánh dấu lại.');
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
        } else {
            $data['completed_at'] = null;
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

    private function syncReminders(TaskAssignmentItem $item, array $reminders): void
    {
        $keptIds = [];
        foreach ($reminders as $r) {
            $moment = $r['moment'] ?? 'before';
            $offset = (int) ($r['offset_minutes'] ?? 0);
            $channels = array_values(array_unique(array_map('strtoupper', (array) ($r['channels'] ?? []))));

            $remindAt = $item->end_at
                ? match ($moment) {
                    'before' => $offset ? $item->end_at->copy()->subMinutes($offset) : null,
                    'on'     => $item->end_at->copy(),
                    'after'  => $offset ? $item->end_at->copy()->addMinutes($offset) : null,
                    default  => null,
                }
                : null;

            $attributes = [
                'task_assignment_item_id' => $item->id,
                'moment'                  => $moment,
                'offset_minutes'          => $offset,
                'channels'                => $channels,
                'source'                  => 'CUSTOM',
                'status'                  => 'pending',
                'remind_at'               => $remindAt,
            ];

            if (! empty($r['id'])) {
                $existing = TaskAssignmentReminder::where('id', $r['id'])
                    ->where('task_assignment_item_id', $item->id)
                    ->where('source', 'CUSTOM')
                    ->first();
                if ($existing) {
                    if ($existing->status !== 'pending') {
                        $attributes['status'] = $existing->status;
                    }
                    $existing->update($attributes);
                    $keptIds[] = $existing->id;
                } else {
                    $new = TaskAssignmentReminder::create($attributes);
                    $keptIds[] = $new->id;
                }
            } else {
                $new = TaskAssignmentReminder::create($attributes);
                $keptIds[] = $new->id;
            }
        }

        $item->reminders()->where('source', 'CUSTOM')
            ->whereNotIn('id', $keptIds)
            ->delete();
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

    private const ADMIN_ROLES = ['Quản trị', 'Super Admin', 'Admin'];

    private function applyDepartmentRestriction(array $filters): array
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
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
        $todo = TaskProgressStatusEnum::Todo->value;
        $inProgress = TaskProgressStatusEnum::InProgress->value;
        $paused = TaskProgressStatusEnum::Paused->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $query = DB::table('task_assignment_items as ti')
            ->join('task_assignment_item_types as tit', 'tit.id', '=', 'ti.task_assignment_item_type_id')
            ->where('tit.status', 'active')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereExists(function ($sub) use ($v) {
                $sub->select(DB::raw(1))
                    ->from('task_assignment_item_user')
                    ->whereColumn('task_assignment_item_user.task_assignment_item_id', 'ti.id')
                    ->where('task_assignment_item_user.department_id', $v);
            }))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('ti.priority', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('ti.created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('ti.created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->groupBy('ti.task_assignment_item_type_id', 'tit.name')
            ->selectRaw('ti.task_assignment_item_type_id as item_type_id, tit.name as item_type_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$todo}' THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$inProgress}' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$paused}' THEN 1 ELSE 0 END) as paused")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$cancelled}' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN ti.processing_status NOT IN ('{$done}','{$cancelled}') AND (ti.deadline_type != '{$hasDeadline}' OR ti.end_at IS NULL OR ti.end_at >= NOW()) THEN 1 ELSE 0 END) as upcoming")
            ->selectRaw("SUM(CASE WHEN ti.deadline_type = '{$hasDeadline}' AND ti.end_at < NOW() AND ti.processing_status IN ('{$todo}','{$inProgress}','{$paused}') THEN 1 ELSE 0 END) as overdue")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND ti.deadline_type = '{$hasDeadline}' AND ti.completed_at > ti.end_at THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND ti.deadline_type = '{$hasDeadline}' AND DATE(ti.completed_at) < DATE(ti.end_at) THEN 1 ELSE 0 END) as early")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND (ti.deadline_type != '{$hasDeadline}' OR ti.end_at IS NULL OR DATE(ti.completed_at) = DATE(ti.end_at)) THEN 1 ELSE 0 END) as on_time");

        $rows = $query->get()->keyBy('item_type_id');

        return $itemTypes->map(function ($type) use ($rows) {
            $row = $rows->get($type->id);
            return [
                'item_type_id' => $type->id,
                'item_type_name' => $type->name,
                'total' => (int) ($row->total ?? 0),
                'todo' => (int) ($row->todo ?? 0),
                'in_progress' => (int) ($row->in_progress ?? 0),
                'paused' => (int) ($row->paused ?? 0),
                'done' => (int) ($row->done ?? 0),
                'cancelled' => (int) ($row->cancelled ?? 0),
                'timing_stats' => [
                    'upcoming' => (int) ($row->upcoming ?? 0),
                    'overdue' => (int) ($row->overdue ?? 0),
                    'late' => (int) ($row->late ?? 0),
                    'early' => (int) ($row->early ?? 0),
                    'on_time' => (int) ($row->on_time ?? 0),
                    'cancelled' => (int) ($row->cancelled ?? 0),
                ],
            ];
        })->all();
    }

    public function statsByDepartment(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $departments = TaskAssignmentDepartment::query()
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'name', 'status']);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $todo = TaskProgressStatusEnum::Todo->value;
        $inProgress = TaskProgressStatusEnum::InProgress->value;
        $paused = TaskProgressStatusEnum::Paused->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $toDateEnd = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

        $query = DB::table('task_assignment_items as ti')
            ->join(DB::raw('(SELECT DISTINCT task_assignment_item_id, department_id FROM task_assignment_item_user) as tiu'),
                'tiu.task_assignment_item_id', '=', 'ti.id')
            ->join('task_assignment_departments as td', 'td.id', '=', 'tiu.department_id')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('tiu.department_id', $v))
            ->when($filters['processing_status'] ?? null, fn ($q, $v) => $q->where('ti.processing_status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('ti.priority', $v))
            ->when($filters['deadline_type'] ?? null, fn ($q, $v) => $q->where('ti.deadline_type', $v))
            ->when($filters['task_assignment_item_type_id'] ?? null, fn ($q, $v) => $q->where('ti.task_assignment_item_type_id', $v))
            ->when($fromDate, fn ($q, $v) => $q->where('ti.created_at', '>=', $v))
            ->when($toDate, fn ($q, $v) => $q->where('ti.created_at', '<=', $toDateEnd))
            ->groupBy('tiu.department_id', 'td.name')
            ->selectRaw('tiu.department_id, td.name as department_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$todo}' THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$inProgress}' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$paused}' THEN 1 ELSE 0 END) as paused")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$cancelled}' THEN 1 ELSE 0 END) as cancelled")
            ->selectRaw("SUM(CASE WHEN ti.processing_status NOT IN ('{$done}','{$cancelled}') AND (ti.deadline_type != '{$hasDeadline}' OR ti.end_at IS NULL OR ti.end_at >= NOW()) THEN 1 ELSE 0 END) as upcoming")
            ->selectRaw("SUM(CASE WHEN ti.deadline_type = '{$hasDeadline}' AND ti.end_at < NOW() AND ti.processing_status IN ('{$todo}','{$inProgress}','{$paused}') THEN 1 ELSE 0 END) as overdue")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND ti.deadline_type = '{$hasDeadline}' AND ti.completed_at > ti.end_at THEN 1 ELSE 0 END) as late")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND ti.deadline_type = '{$hasDeadline}' AND DATE(ti.completed_at) < DATE(ti.end_at) THEN 1 ELSE 0 END) as early")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND (ti.deadline_type != '{$hasDeadline}' OR ti.end_at IS NULL OR DATE(ti.completed_at) = DATE(ti.end_at)) THEN 1 ELSE 0 END) as on_time");

        if ($fromDate && $toDate) {
            $query->selectRaw('SUM(CASE WHEN ti.created_at >= ? AND ti.created_at <= ? THEN 1 ELSE 0 END) as new_in_period', [$fromDate, $toDateEnd])
                ->selectRaw("SUM(CASE WHEN ti.processing_status = '{$done}' AND ti.completed_at >= ? AND ti.completed_at <= ? THEN 1 ELSE 0 END) as done_in_period", [$fromDate, $toDateEnd]);
        } else {
            $query->selectRaw('NULL as new_in_period, NULL as done_in_period');
        }

        $rows = $query->get()->keyBy('department_id');

        return $departments->map(function ($dept) use ($rows, $fromDate, $toDate) {
            $row = $rows->get($dept->id);
            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'department_code' => $dept->code ?? null,
                'total' => (int) ($row->total ?? 0),
                'todo' => (int) ($row->todo ?? 0),
                'in_progress' => (int) ($row->in_progress ?? 0),
                'paused' => (int) ($row->paused ?? 0),
                'done' => (int) ($row->done ?? 0),
                'cancelled' => (int) ($row->cancelled ?? 0),
                'timing_stats' => [
                    'upcoming' => (int) ($row->upcoming ?? 0),
                    'overdue' => (int) ($row->overdue ?? 0),
                    'late' => (int) ($row->late ?? 0),
                    'early' => (int) ($row->early ?? 0),
                    'on_time' => (int) ($row->on_time ?? 0),
                    'cancelled' => (int) ($row->cancelled ?? 0),
                ],
                'new_in_period' => ($fromDate && $toDate) ? (int) ($row->new_in_period ?? 0) : null,
                'done_in_period' => ($fromDate && $toDate) ? (int) ($row->done_in_period ?? 0) : null,
            ];
        })->all();
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

        $query->groupBy('tiu.user_id', 'u.name')
            ->selectRaw('tiu.user_id, u.name as user_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'todo' THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'paused' THEN 1 ELSE 0 END) as paused")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as cancelled", [$cancelled])
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

            $totalUpToMonth = (clone $baseQuery)
                ->where('created_at', '<=', $monthEnd)
                ->count();

            $results[] = [
                'month' => $monthKey,
                'total' => $totalUpToMonth,
                'done' => $doneInMonth,
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
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'done' THEN 1 ELSE 0 END) as done")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->orderBy('td.issue_date', 'desc')
            ->get();

        return $results->map(fn ($row) => [
            'document_id' => $row->document_id,
            'document_name' => $row->document_name,
            'issue_date' => $row->issue_date,
            'total_items' => (int) $row->total_items,
            'done' => (int) $row->done,
            'in_progress' => (int) $row->in_progress,
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
            // Filter from_date/to_date áp lên `end_at` (deadline) — "tasks có deadline trong khoảng này".
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('end_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('end_at', '<=', Carbon::parse($v)->endOfDay()))
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
            // Filter from_date/to_date áp lên `end_at` — AND với khoảng `days` (giao 2 điều kiện).
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('end_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('end_at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderBy('end_at', 'asc')
            ->paginate($limit);
    }
}
