<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Exports\ItemsExport;
use App\Modules\TaskAssignment\Imports\ItemsImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
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
}
