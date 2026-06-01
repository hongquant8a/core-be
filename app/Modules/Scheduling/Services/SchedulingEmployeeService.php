<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Exports\SchedulingEmployeeExport;
use App\Modules\Scheduling\Imports\SchedulingEmployeeImport;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use Illuminate\Http\Exceptions\HttpResponseException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchedulingEmployeeService
{
    public function stats(array $filters): array
    {
        $base = SchedulingEmployee::filter($filters);
        return [
            'total'    => (clone $base)->count(),
            'active'   => (clone $base)->where('status', true)->count(),
            'inactive' => (clone $base)->where('status', false)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return SchedulingEmployee::with(['user', 'groups'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(SchedulingEmployee $employee): SchedulingEmployee
    {
        return $employee->load(['user', 'groups']);
    }

    public function store(array $data): SchedulingEmployee
    {
        $data['organization_id'] = getPermissionsTeamId();
        return SchedulingEmployee::create($data)->load(['user', 'groups']);
    }

    public function update(SchedulingEmployee $employee, array $data): SchedulingEmployee
    {
        $employee->update($data);
        return $employee->load(['user', 'groups']);
    }

    public function destroy(SchedulingEmployee $employee): void
    {
        $this->guardAgainstDeletion([$employee->user_id], $employee->organization_id);
        $employee->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        $orgId = getPermissionsTeamId();
        $employees = SchedulingEmployee::whereIn('id', $ids)->get(['id', 'user_id']);
        $userIds = $employees->pluck('user_id')->filter()->all();

        if ($userIds) {
            $this->guardAgainstDeletion($userIds, $orgId);
        }

        SchedulingEmployee::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, bool $status): void
    {
        SchedulingEmployee::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(SchedulingEmployee $employee, bool $status): SchedulingEmployee
    {
        $employee->update(['status' => $status]);
        return $employee->load(['user', 'groups']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(
            new SchedulingEmployeeExport($filters),
            ExportFilename::make('nhan-vien-lich-cong-tac')
        );
    }

    public function import($file): array
    {
        $orgId = getPermissionsTeamId();
        $importer = new SchedulingEmployeeImport($orgId);
        Excel::import($importer, $file);

        return [
            'success' => true,
            'imported_count' => $importer->getSuccessCount(),
        ];
    }

    public function syncGroups(SchedulingEmployee $employee, array $groupIds): void
    {
        $employee->groups()->sync($groupIds);
    }

    /**
     * Kiểm tra user_id còn ràng buộc là chủ trì lịch công tác. Throw HttpResponseException 409.
     */
    private function guardAgainstDeletion(array $userIds, ?int $orgId): void
    {
        $orgId = $orgId ?? getPermissionsTeamId();

        $scheduleUsages = Schedule::whereIn('host_user_id', $userIds)
            ->when($orgId, fn ($q, $v) => $q->where('organization_id', $v))
            ->select('host_user_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('host_user_id')
            ->pluck('cnt', 'host_user_id');

        $blocking = [];
        foreach ($userIds as $userId) {
            $scheduleCount = (int) ($scheduleUsages[$userId] ?? 0);
            if ($scheduleCount > 0) {
                $blocking[$userId] = [
                    'schedule_count' => $scheduleCount,
                ];
            }
        }

        if (! $blocking) {
            return;
        }

        $names = \App\Modules\Core\Models\User::whereIn('id', array_keys($blocking))->pluck('name', 'id');
        $messages = [];
        foreach ($blocking as $uId => $cnts) {
            $name = $names[$uId] ?? "ID $uId";
            $messages[] = "Nhân viên \"{$name}\" đang chủ trì {$cnts['schedule_count']} lịch công tác.";
        }

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Không thể xóa các nhân viên sau do ràng buộc dữ liệu:',
                'errors' => $messages,
            ], 409)
        );
    }
}
