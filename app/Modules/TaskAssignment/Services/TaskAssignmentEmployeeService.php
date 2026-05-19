<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Exports\EmployeeExport;
use App\Modules\TaskAssignment\Imports\EmployeeImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentEmployeeService
{
    public function publicOptions(array $filters)
    {
        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'created_at',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return TaskAssignmentEmployee::with('user:id,name,email,user_name')
            ->filter($publicFilters)
            ->get();
    }

    public function stats(array $filters): array
    {
        $base = TaskAssignmentEmployee::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', StatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return TaskAssignmentEmployee::with(['user.media', 'creator.media', 'editor.media', 'departmentMemberships.department'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentEmployee $employee): TaskAssignmentEmployee
    {
        return $employee->load(['user.media', 'creator.media', 'editor.media', 'departmentMemberships.department']);
    }

    public function store(array $validated): TaskAssignmentEmployee
    {
        return TaskAssignmentEmployee::create($validated)
            ->load(['user.media', 'creator.media', 'editor.media', 'departmentMemberships.department']);
    }

    public function update(TaskAssignmentEmployee $employee, array $validated): TaskAssignmentEmployee
    {
        $employee->update($validated);

        return $employee->load(['user.media', 'creator.media', 'editor.media', 'departmentMemberships.department']);
    }

    /**
     * Xóa nhân viên — soft block (409) nếu vẫn thuộc dept hoặc còn assignment.
     */
    public function destroy(TaskAssignmentEmployee $employee): void
    {
        $this->guardAgainstDeletion([$employee->user_id], $employee->organization_id);
        $employee->delete();
    }

    /**
     * Bulk delete — guard kiểm tra tất cả user_id 1 lượt để báo lỗi gộp.
     */
    public function bulkDestroy(array $ids): void
    {
        $orgId = getPermissionsTeamId();
        $employees = TaskAssignmentEmployee::whereIn('id', $ids)->get(['id', 'user_id']);
        $userIds = $employees->pluck('user_id')->all();

        if ($userIds) {
            $this->guardAgainstDeletion($userIds, $orgId);
        }

        TaskAssignmentEmployee::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        TaskAssignmentEmployee::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(TaskAssignmentEmployee $employee, string $status): TaskAssignmentEmployee
    {
        $employee->update(['status' => $status]);

        return $employee->load(['user.media', 'creator.media', 'editor.media', 'departmentMemberships.department']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new EmployeeExport($filters), ExportFilename::make('nhan-vien-giao-viec'));
    }

    public function import($file): void
    {
        Excel::import(new EmployeeImport, $file);
    }

    /**
     * Kiểm tra user_id còn ràng buộc dept hoặc assignment. Throw HttpResponseException 409.
     *
     * @param  array<int>  $userIds
     */
    private function guardAgainstDeletion(array $userIds, ?int $orgId): void
    {
        $orgId = $orgId ?? getPermissionsTeamId();

        $deptUsages = TaskAssignmentUser::whereIn('user_id', $userIds)
            ->when($orgId, fn ($q, $v) => $q->where('organization_id', $v))
            ->select('user_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $taskUsages = \Illuminate\Support\Facades\DB::table('task_assignment_item_user')
            ->whereIn('user_id', $userIds)
            ->select('user_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id');

        $blocking = [];
        foreach ($userIds as $userId) {
            $deptCount = (int) ($deptUsages[$userId] ?? 0);
            $taskCount = (int) ($taskUsages[$userId] ?? 0);
            if ($deptCount > 0 || $taskCount > 0) {
                $blocking[$userId] = [
                    'dept_count' => $deptCount,
                    'task_count' => $taskCount,
                ];
            }
        }

        if (! $blocking) {
            return;
        }

        $details = collect($blocking)->map(fn ($v, $userId) => "user #{$userId} ({$v['dept_count']} phòng ban, {$v['task_count']} công việc)")
            ->values()
            ->implode('; ');

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => "Không thể xóa nhân viên đang thuộc phòng ban hoặc còn công việc: {$details}",
            'errors' => $blocking,
        ], 409));
    }
}
