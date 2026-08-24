<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Exports\DepartmentExport;
use App\Modules\TaskAssignment\Imports\DepartmentImport;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use Illuminate\Support\Facades\DB;
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

        return TaskAssignmentDepartment::select(['id', 'name', 'description'])->filter($publicFilters)->get();
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
            ->withCount('employeeMemberships')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(TaskAssignmentDepartment $department): TaskAssignmentDepartment
    {
        return $department->load(['creator.media', 'editor.media', 'employeeMemberships.employee.user'])
            ->loadCount('employeeMemberships');
    }

    /**
     * Tạo phòng ban, kèm luôn danh sách nhân viên nếu form gửi lên.
     * Không có endpoint quan hệ riêng — thành viên là một trường của phòng ban.
     */
    public function store(array $validated): TaskAssignmentDepartment
    {
        return DB::transaction(function () use ($validated) {
            $employeeIds = $validated['employee_ids'] ?? null;
            $representativeId = $validated['representative_employee_id'] ?? null;

            $department = TaskAssignmentDepartment::create(
                collect($validated)->except(['employee_ids', 'representative_employee_id'])->all()
            );

            if ($employeeIds !== null) {
                $this->syncEmployees($department, $employeeIds, $representativeId);
            }

            return $this->freshWithRelations($department);
        });
    }

    public function update(TaskAssignmentDepartment $department, array $validated): TaskAssignmentDepartment
    {
        return DB::transaction(function () use ($department, $validated) {
            $employeeIds = $validated['employee_ids'] ?? null;
            $representativeId = $validated['representative_employee_id'] ?? null;

            $department->update(
                collect($validated)->except(['employee_ids', 'representative_employee_id'])->all()
            );

            // Chỉ đụng tới thành viên khi form thực sự gửi `employee_ids`,
            // để PATCH một trường lẻ không xoá sạch thành viên.
            if ($employeeIds !== null) {
                $this->syncEmployees($department, $employeeIds, $representativeId);
            }

            return $this->freshWithRelations($department);
        });
    }

    /**
     * Đặt lại toàn bộ thành viên của phòng ban theo danh sách employee id.
     *
     * @param  array<int>  $employeeIds
     */
    private function syncEmployees(TaskAssignmentDepartment $department, array $employeeIds, ?int $representativeId = null): void
    {
        $current = $department->employeeMemberships()->pluck('task_assignment_employee_id')->all();

        $toRemove = array_diff($current, $employeeIds);
        $toAdd = array_diff($employeeIds, $current);

        if ($toRemove) {
            $department->employeeMemberships()
                ->whereIn('task_assignment_employee_id', $toRemove)
                ->delete();
        }

        foreach ($toAdd as $employeeId) {
            TaskAssignmentEmployeeDepartment::create([
                'task_assignment_employee_id' => $employeeId,
                'task_assignment_department_id' => $department->id,
                'organization_id' => getPermissionsTeamId(),
            ]);
        }

        $this->setRepresentative($department, $representativeId);
    }

    private function freshWithRelations(TaskAssignmentDepartment $department): TaskAssignmentDepartment
    {
        return $department->load(['creator.media', 'editor.media', 'employeeMemberships.employee.user'])
            ->loadCount('employeeMemberships');
    }

    public function destroy(TaskAssignmentDepartment $department): void
    {
        $this->guardAgainstDeletion([$department->id]);

        $department->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        $this->guardAgainstDeletion($ids);

        TaskAssignmentDepartment::whereIn('id', $ids)->delete();
    }

    /**
     * Chặn xoá phòng ban còn thành viên hoặc còn dòng phân công công việc.
     *
     * Khoá ngoại đã là RESTRICT nên DB cũng chặn, nhưng chặn ở đây để trả về
     * thông báo tiếng Việt thay vì lỗi SQL.
     *
     * @param  array<int>  $ids
     */
    private function guardAgainstDeletion(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $withEmployees = TaskAssignmentEmployeeDepartment::whereIn('task_assignment_department_id', $ids)
            ->distinct()
            ->pluck('task_assignment_department_id');

        $withTasks = DB::table('task_assignment_item_user')
            ->whereIn('department_id', $ids)
            ->distinct()
            ->pluck('department_id');

        $blockedIds = $withEmployees->merge($withTasks)->unique();

        if ($blockedIds->isEmpty()) {
            return;
        }

        $names = TaskAssignmentDepartment::whereIn('id', $blockedIds)->pluck('name')->implode(', ');

        throw ValidationException::withMessages([
            'id' => ["Không thể xóa phòng ban còn nhân viên hoặc còn công việc đã giao: {$names}. Hãy chuyển hết nhân viên và công việc trước."],
        ]);
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        TaskAssignmentDepartment::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(TaskAssignmentDepartment $department, string $status): TaskAssignmentDepartment
    {
        $department->update(['status' => $status]);

        return $department->load(['creator.media', 'editor.media'])
            ->loadCount('employeeMemberships');
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DepartmentExport($filters), ExportFilename::make('phong-ban-giao-viec'));
    }

    public function import($file): void
    {
        Excel::import(new DepartmentImport, $file);
    }

    /** Đặt người đại diện phòng ban; `null` nghĩa là bỏ trống. */
    private function setRepresentative(TaskAssignmentDepartment $department, ?int $employeeId): void
    {
        $membership = null;

        if ($employeeId !== null) {
            $membership = $department->employeeMemberships()
                ->where('task_assignment_employee_id', $employeeId)
                ->first();

            if (! $membership) {
                throw ValidationException::withMessages([
                    'representative_employee_id' => 'Người đại diện phải thuộc danh sách nhân viên của phòng ban.',
                ]);
            }
        }

        $department->employeeMemberships()->update(['is_representative' => false]);

        $membership?->update(['is_representative' => true]);
    }
}
