<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchedulingEmployeeGroupService
{
    public function stats(array $filters): array
    {
        $base = SchedulingEmployeeGroup::filter($filters);
        return [
            'total'    => (clone $base)->count(),
            'active'   => (clone $base)->where('status', true)->count(),
            'inactive' => (clone $base)->where('status', false)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return SchedulingEmployeeGroup::withCount('members')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(SchedulingEmployeeGroup $group): SchedulingEmployeeGroup
    {
        return $group->load(['members.user']);
    }

    public function store(array $data): SchedulingEmployeeGroup
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = getPermissionsTeamId();
            $group = SchedulingEmployeeGroup::create($data);

            if (isset($data['employee_ids'])) {
                $this->syncMembers($group, $data['employee_ids']);
            }

            return $this->show($group);
        });
    }

    public function update(SchedulingEmployeeGroup $group, array $data): SchedulingEmployeeGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update($data);

            if (isset($data['employee_ids'])) {
                $this->syncMembers($group, $data['employee_ids']);
            }

            return $this->show($group);
        });
    }

    public function destroy(SchedulingEmployeeGroup $group): void
    {
        $group->members()->detach();
        $group->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            DB::table('scheduling_employee_group_members')->whereIn('scheduling_employee_group_id', $ids)->delete();
            SchedulingEmployeeGroup::whereIn('id', $ids)->delete();
        });
    }

    public function bulkUpdateStatus(array $ids, bool $status): void
    {
        SchedulingEmployeeGroup::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(SchedulingEmployeeGroup $group, bool $status): SchedulingEmployeeGroup
    {
        $group->update(['status' => $status]);
        return $this->show($group);
    }

    public function syncMembers(SchedulingEmployeeGroup $group, array $employeeIds): void
    {
        $group->members()->sync($employeeIds);
    }
}
