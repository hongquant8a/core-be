<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Exports\DepartmentExport;
use App\Modules\TaskAssignment\Imports\DepartmentImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Illuminate\Validation\ValidationException;
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
        return TaskAssignmentDepartment::with(['creator.media', 'editor.media'])
            ->withCount('taskAssignmentUsers')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentDepartment $department): TaskAssignmentDepartment
    {
        return $department->load(['creator.media', 'editor.media'])
            ->loadCount('taskAssignmentUsers');
    }

    public function store(array $validated): TaskAssignmentDepartment
    {
        return TaskAssignmentDepartment::create($validated)
            ->load(['creator.media', 'editor.media'])
            ->loadCount('taskAssignmentUsers');
    }

    public function update(TaskAssignmentDepartment $department, array $validated): TaskAssignmentDepartment
    {
        $department->update($validated);

        return $department->load(['creator.media', 'editor.media'])
            ->loadCount('taskAssignmentUsers');
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

        return $department->load(['creator.media', 'editor.media'])
            ->loadCount('taskAssignmentUsers');
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DepartmentExport($filters), ExportFilename::make('phong-ban-giao-viec'));
    }

    public function import($file): void
    {
        Excel::import(new DepartmentImport, $file);
    }

    public function getUsers(TaskAssignmentDepartment $department)
    {
        return $department->taskAssignmentUsers()
            ->with('user.media')
            ->where('status', 'active')
            ->get();
    }

    public function syncUsers(TaskAssignmentDepartment $department, array $userIds, ?int $representativeUserId = null): void
    {
        $orgId = getPermissionsTeamId();

        \Illuminate\Support\Facades\DB::transaction(function () use ($department, $userIds, $representativeUserId, $orgId) {
            $currentUserIds = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('organization_id', $orgId)
                ->pluck('user_id')
                ->all();

            $toRemove = array_diff($currentUserIds, $userIds);
            $toAdd = array_diff($userIds, $currentUserIds);

            if ($toRemove) {
                TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                    ->where('organization_id', $orgId)
                    ->whereIn('user_id', $toRemove)
                    ->delete();
            }

            foreach ($toAdd as $userId) {
                $hasPrimary = TaskAssignmentUser::where('user_id', $userId)
                    ->where('organization_id', $orgId)
                    ->where('is_primary', true)
                    ->exists();

                TaskAssignmentUser::create([
                    'user_id' => $userId,
                    'task_assignment_department_id' => $department->id,
                    'organization_id' => $orgId,
                    'status' => 'active',
                    'is_primary' => ! $hasPrimary,
                ]);
            }

            if ($representativeUserId !== null) {
                $this->setRepresentative($department, $representativeUserId);
            }
        });
    }

    private function setRepresentative(TaskAssignmentDepartment $department, ?int $userId): void
    {
        $orgId = getPermissionsTeamId();

        if ($userId !== null) {
            $exists = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'representative_user_id' => 'Người đại diện phải thuộc danh sách thành viên.',
                ]);
            }
        }

        TaskAssignmentUser::where('task_assignment_department_id', $department->id)
            ->where('organization_id', $orgId)
            ->update(['is_representative' => false]);

        if ($userId !== null) {
            TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->update(['is_representative' => true]);
        }
    }

    public function setPrimary(int $userId, int $departmentId): void
    {
        $orgId = getPermissionsTeamId();

        $target = TaskAssignmentUser::where('user_id', $userId)
            ->where('task_assignment_department_id', $departmentId)
            ->where('organization_id', $orgId)
            ->firstOrFail();

        \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $target, $orgId) {
            TaskAssignmentUser::where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->update(['is_primary' => false]);

            $target->update(['is_primary' => true]);
        });
    }

    public function removeUser(TaskAssignmentDepartment $department, int $userId): void
    {
        $orgId = getPermissionsTeamId();

        \Illuminate\Support\Facades\DB::transaction(function () use ($department, $userId, $orgId) {
            $removed = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('user_id', $userId)
                ->first();

            if (! $removed) {
                return;
            }

            $wasPrimary = (bool) $removed->is_primary;
            $removed->delete();

            if ($wasPrimary) {
                TaskAssignmentUser::where('user_id', $userId)
                    ->where('organization_id', $orgId)
                    ->limit(1)
                    ->update(['is_primary' => true]);
            }
        });
    }
}
