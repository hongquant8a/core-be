<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchedulingEmployeeService
{
    public function publicOptions(array $filters)
    {
        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'created_at',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return SchedulingEmployee::with('user:id,name,email,user_name')
            ->filter($publicFilters)
            ->get();
    }

    public function stats(array $filters): array
    {
        $base = SchedulingEmployee::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', StatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return SchedulingEmployee::with(['user.media', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(SchedulingEmployee $employee): SchedulingEmployee
    {
        return $employee->load(['user.media', 'creator.media', 'editor.media']);
    }

    public function store(array $validated): SchedulingEmployee
    {
        return SchedulingEmployee::create($validated)
            ->load(['user.media', 'creator.media', 'editor.media']);
    }

    public function update(SchedulingEmployee $employee, array $validated): SchedulingEmployee
    {
        $employee->update($validated);

        return $employee->load(['user.media', 'creator.media', 'editor.media']);
    }

    /**
     * Xóa nhân viên — soft block (409) nếu vẫn đang chủ trì lịch họp/công tác nào.
     */
    public function destroy(SchedulingEmployee $employee): void
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
        $employees = SchedulingEmployee::whereIn('id', $ids)->get(['id', 'user_id']);
        $userIds = $employees->pluck('user_id')->all();

        if ($userIds) {
            $this->guardAgainstDeletion($userIds, $orgId);
        }

        SchedulingEmployee::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        SchedulingEmployee::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(SchedulingEmployee $employee, string $status): SchedulingEmployee
    {
        $employee->update(['status' => $status]);

        return $employee->load(['user.media', 'creator.media', 'editor.media']);
    }

    /**
     * Kiểm tra user_id còn ràng buộc là chủ trì lịch công tác. Throw HttpResponseException 409.
     *
     * @param  array<int>  $userIds
     */
    private function guardAgainstDeletion(array $userIds, ?int $orgId): void
    {
        $orgId = $orgId ?? getPermissionsTeamId();

        $scheduleUsages = Schedule::whereIn('host_id', $userIds)
            ->when($orgId, fn ($q, $v) => $q->where('organization_id', $v))
            ->select('host_id')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('host_id')
            ->pluck('cnt', 'host_id');

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
