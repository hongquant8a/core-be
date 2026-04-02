<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\TaskAssignment\Exports\DepartmentExport;
use App\Modules\TaskAssignment\Imports\DepartmentImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentDepartmentService
{
    public function publicList(array $filters)
    {
        $publicFilters = [
            ...$filters,
            'status' => \App\Modules\Core\Enums\StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'name',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return TaskAssignmentDepartment::filter($publicFilters)->get();
    }

    public function publicOptions(array $filters)
    {
        $publicFilters = [
            ...$filters,
            'status' => \App\Modules\Core\Enums\StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'name',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return TaskAssignmentDepartment::select(['id', 'name', 'code', 'description'])->filter($publicFilters)->get();
    }

    public function stats(array $filters): array
    {
        $base = TaskAssignmentDepartment::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', StatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentDepartment::with(['creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentDepartment $department): TaskAssignmentDepartment
    {
        return $department->load(['creator', 'editor']);
    }

    public function store(array $validated): TaskAssignmentDepartment
    {
        return TaskAssignmentDepartment::create($validated)->load(['creator', 'editor']);
    }

    public function update(TaskAssignmentDepartment $department, array $validated): TaskAssignmentDepartment
    {
        $department->update($validated);

        return $department->load(['creator', 'editor']);
    }

    public function destroy(TaskAssignmentDepartment $department): void
    {
        $department->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        TaskAssignmentDepartment::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        TaskAssignmentDepartment::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(TaskAssignmentDepartment $department, string $status): TaskAssignmentDepartment
    {
        $department->update(['status' => $status]);

        return $department->load(['creator', 'editor']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DepartmentExport($filters), 'task-assignment-departments.xlsx');
    }

    public function import($file): void
    {
        Excel::import(new DepartmentImport, $file);
    }
}
