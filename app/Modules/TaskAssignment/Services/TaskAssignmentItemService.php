<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Exports\ItemsExport;
use App\Modules\TaskAssignment\Imports\ItemsImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentItemService
{
    public function stats(array $filters): array
    {
        $base = TaskAssignmentItem::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'todo' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
            'in_progress' => (clone $base)->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
            'done' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Done->value)->count(),
            'overdue' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Overdue->value)->count(),
            'paused' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
            'cancelled' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Cancelled->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentItem::with(['document', 'itemType', 'departments', 'users'])
            ->withCount('reports')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentItem $item): TaskAssignmentItem
    {
        return $item->load(['document', 'itemType', 'departments', 'users', 'reports', 'creator', 'editor']);
    }

    public function store(array $validated): TaskAssignmentItem
    {
        return DB::transaction(function () use ($validated) {
            $departments = $validated['departments'] ?? [];
            $users = $validated['users'] ?? [];

            $data = collect($validated)->except(['departments', 'users'])->all();
            $item = TaskAssignmentItem::create($data);

            if (! empty($departments)) {
                $this->syncDepartments($item, $departments);
            }

            if (! empty($users)) {
                $this->syncUsers($item, $users);
            }

            return $item->load(['document', 'itemType', 'departments', 'users', 'creator', 'editor']);
        });
    }

    public function update(TaskAssignmentItem $item, array $validated): TaskAssignmentItem
    {
        return DB::transaction(function () use ($item, $validated) {
            $departments = $validated['departments'] ?? null;
            $users = $validated['users'] ?? null;

            $data = collect($validated)->except(['departments', 'users'])->all();
            $item->update($data);

            if ($departments !== null) {
                $this->syncDepartments($item, $departments);
            }

            if ($users !== null) {
                $this->syncUsers($item, $users);
            }

            return $item->load(['document', 'itemType', 'departments', 'users', 'creator', 'editor']);
        });
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

        return $item->load(['document', 'itemType', 'departments', 'users', 'creator', 'editor']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new ItemsExport($filters), 'task-assignment-items.xlsx');
    }

    public function import($file, int $documentId): void
    {
        Excel::import(new ItemsImport($documentId), $file);
    }

    public function updateProgress(TaskAssignmentItem $item, array $validated): TaskAssignmentItem
    {
        $status = $validated['processing_status'] ?? $item->processing_status;
        $percent = $validated['completion_percent'] ?? $item->completion_percent;

        // Rule: done -> 100%, set completed_at
        if ($status === TaskProgressStatusEnum::Done->value) {
            $percent = 100;
            $item->completed_at = $item->completed_at ?? now();
        }
        // Rule: 100% -> done, set completed_at
        elseif ((int) $percent === 100) {
            $status = TaskProgressStatusEnum::Done->value;
            $item->completed_at = $item->completed_at ?? now();
        }
        // Rule: reopen from done -> clear completed_at
        elseif ($item->processing_status === TaskProgressStatusEnum::Done->value && $status !== TaskProgressStatusEnum::Done->value) {
            $item->completed_at = null;
        }

        $item->processing_status = $status;
        $item->completion_percent = $percent;
        $item->save();

        return $item->load(['document', 'itemType', 'departments', 'users', 'creator', 'editor']);
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

    private function syncDepartments(TaskAssignmentItem $item, array $departments): void
    {
        $syncData = [];
        foreach ($departments as $dept) {
            $syncData[$dept['department_id']] = ['role' => $dept['role']];
        }
        $item->departments()->sync($syncData);
    }

    private function syncUsers(TaskAssignmentItem $item, array $users): void
    {
        $syncData = [];
        foreach ($users as $user) {
            $syncData[$user['user_id']] = [
                'department_id' => $user['department_id'],
                'assignment_role' => $user['assignment_role'],
                'assignment_status' => 'assigned',
                'assigned_at' => now(),
            ];
        }
        $item->users()->sync($syncData);
    }

    private function applyDepartmentRestriction(array $filters): array
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['Quản trị', 'Super Admin', 'Admin'])) {
            $filters['department_id'] = $user->task_assignment_department_id;
        }

        return $filters;
    }

    public function statsByDepartment(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $departments = TaskAssignmentDepartment::where('status', 'active')
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'name', 'code']);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        return $departments->map(function ($dept) use ($filters, $done, $cancelled, $hasDeadline) {
            $base = TaskAssignmentItem::whereHas('departments', fn ($q) => $q->where('department_id', $dept->id))
                ->when($filters['processing_status'] ?? null, fn ($q, $v) => $q->where('processing_status', $v))
                ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
                ->when($filters['deadline_type'] ?? null, fn ($q, $v) => $q->where('deadline_type', $v))
                ->when($filters['task_assignment_item_type_id'] ?? null, fn ($q, $v) => $q->where('task_assignment_item_type_id', $v))
                ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
                ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));

            $total = (clone $base)->count();

            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'department_code' => $dept->code,
                'total' => $total,
                'todo' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Todo->value)->count(),
                'in_progress' => (clone $base)->where('processing_status', TaskProgressStatusEnum::InProgress->value)->count(),
                'done' => (clone $base)->where('processing_status', $done)->count(),
                'overdue' => (clone $base)->where('deadline_type', $hasDeadline)
                    ->where('end_at', '<', now())
                    ->whereNotIn('processing_status', [$done, $cancelled])->count(),
                'paused' => (clone $base)->where('processing_status', TaskProgressStatusEnum::Paused->value)->count(),
                'cancelled' => (clone $base)->where('processing_status', $cancelled)->count(),
            ];
        })->all();
    }

    public function statsByUser(array $filters): array
    {
        $filters = $this->applyDepartmentRestriction($filters);

        $done = TaskProgressStatusEnum::Done->value;
        $cancelled = TaskProgressStatusEnum::Cancelled->value;
        $hasDeadline = TaskDeadlineTypeEnum::HasDeadline->value;

        $query = DB::table('task_assignment_item_user as tiu')
            ->join('task_assignment_items as ti', 'ti.id', '=', 'tiu.task_assignment_item_id')
            ->join('users as u', 'u.id', '=', 'tiu.user_id')
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('tiu.department_id', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('ti.priority', $v))
            ->when($filters['from_date'] ?? null, fn ($q, $v) => $q->where('ti.created_at', '>=', $v))
            ->when($filters['to_date'] ?? null, fn ($q, $v) => $q->where('ti.created_at', '<=', Carbon::parse($v)->endOfDay()));

        if (! empty($filters['processing_status'])) {
            $query->where('ti.processing_status', $filters['processing_status']);
        }

        $results = $query->groupBy('tiu.user_id', 'u.name')
            ->selectRaw('tiu.user_id, u.name as user_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'todo' THEN 1 ELSE 0 END) as todo")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? THEN 1 ELSE 0 END) as done", [$done])
            ->selectRaw("SUM(CASE WHEN ti.deadline_type = ? AND ti.end_at < NOW() AND ti.processing_status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue", [$hasDeadline, $done, $cancelled])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? AND (ti.end_at IS NULL OR ti.completed_at <= ti.end_at) THEN 1 ELSE 0 END) as on_time_count", [$done])
            ->selectRaw("SUM(CASE WHEN ti.processing_status = ? AND ti.completed_at > ti.end_at THEN 1 ELSE 0 END) as overdue_done_count", [$done])
            ->get();

        return $results->map(fn ($row) => [
            'user_id' => $row->user_id,
            'user_name' => $row->user_name,
            'total' => (int) $row->total,
            'todo' => (int) $row->todo,
            'in_progress' => (int) $row->in_progress,
            'done' => (int) $row->done,
            'overdue' => (int) $row->overdue,
            'on_time_count' => (int) $row->on_time_count,
            'overdue_done_count' => (int) $row->overdue_done_count,
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
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('departments', fn ($dq) => $dq->where('department_id', $v)))
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

    public function overdue(array $filters, int $limit)
    {
        $filters = $this->applyDepartmentRestriction($filters);

        return TaskAssignmentItem::with(['document', 'itemType', 'departments', 'users'])
            ->withCount('reports')
            ->where('deadline_type', TaskDeadlineTypeEnum::HasDeadline->value)
            ->where('end_at', '<', now())
            ->whereNotIn('processing_status', [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('departments', fn ($dq) => $dq->where('department_id', $v)))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('user_id', $v)))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->orderBy($filters['sort_by'] ?? 'end_at', $filters['sort_order'] ?? 'asc')
            ->paginate($limit);
    }

    public function upcomingDeadline(array $filters, int $limit)
    {
        $filters = $this->applyDepartmentRestriction($filters);
        $days = (int) ($filters['days'] ?? 3);

        return TaskAssignmentItem::with(['document', 'itemType', 'departments', 'users'])
            ->withCount('reports')
            ->where('deadline_type', TaskDeadlineTypeEnum::HasDeadline->value)
            ->where('end_at', '>=', now())
            ->where('end_at', '<=', now()->addDays($days))
            ->whereNotIn('processing_status', [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value])
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('departments', fn ($dq) => $dq->where('department_id', $v)))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->whereHas('users', fn ($uq) => $uq->where('user_id', $v)))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->orderBy('end_at', 'asc')
            ->paginate($limit);
    }
}
