<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use Illuminate\Support\Facades\DB;

class SchedulingEmployeeGroupService
{
    public function options(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return SchedulingEmployeeGroup::query()
            ->where('status', 'active')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function stats(array $filters = []): array
    {
        $query = SchedulingEmployeeGroup::query();

        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        $items = $query->get(['status']);

        return [
            'total' => $items->count(),
            'active' => $items->where('status', 'active')->count(),
            'inactive' => $items->where('status', 'inactive')->count(),
        ];
    }

    public function index(array $filters = [], int $limit = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = SchedulingEmployeeGroup::query()->withCount('employees');

        $query->filter($filters);

        return $query->paginate($limit);
    }

    public function show(SchedulingEmployeeGroup $group): SchedulingEmployeeGroup
    {
        return $group->load(['employees.user', 'creator', 'editor']);
    }

    public function store(array $data): SchedulingEmployeeGroup
    {
        return DB::transaction(function () use ($data) {
            /** @var SchedulingEmployeeGroup $group */
            $group = SchedulingEmployeeGroup::create($data);

            if (isset($data['employee_ids'])) {
                $this->syncEmployees($group, $data['employee_ids']);
            }

            return $group;
        });
    }

    public function update(SchedulingEmployeeGroup $group, array $data): SchedulingEmployeeGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update($data);

            if (isset($data['employee_ids'])) {
                $this->syncEmployees($group, $data['employee_ids']);
            }

            return $group;
        });
    }

    public function destroy(SchedulingEmployeeGroup $group): void
    {
        $group->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        SchedulingEmployeeGroup::query()->whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        SchedulingEmployeeGroup::query()->whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(SchedulingEmployeeGroup $group, string $status): SchedulingEmployeeGroup
    {
        $group->update(['status' => $status]);
        return $group;
    }

    private function syncEmployees(SchedulingEmployeeGroup $group, array $employeeIds): void
    {
        $syncData = [];
        foreach ($employeeIds as $id) {
            $syncData[$id] = [
                'organization_id' => $group->organization_id ?? auth()->user()?->organization_id
            ];
        }
        $group->employees()->sync($syncData);
    }
}
