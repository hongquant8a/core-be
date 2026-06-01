<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use App\Modules\Scheduling\Requests\{
    StoreSchedulingEmployeeGroupRequest, UpdateSchedulingEmployeeGroupRequest,
    BulkDestroySchedulingEmployeeGroupRequest, BulkUpdateStatusSchedulingEmployeeGroupRequest,
    ChangeStatusSchedulingEmployeeGroupRequest, SyncMembersSchedulingEmployeeGroupRequest
};
use App\Modules\Scheduling\Resources\{SchedulingEmployeeGroupCollection, SchedulingEmployeeGroupResource};
use App\Modules\Scheduling\Services\SchedulingEmployeeGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Scheduling - Nhóm nhân viên lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý các nhóm nhân viên trong phân hệ lịch công tác.
 */
class SchedulingEmployeeGroupController extends Controller
{
    public function __construct(private SchedulingEmployeeGroupService $groupService) {}

    /**
     * Thống kê nhóm nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên nhóm.
     */
    public function stats(FilterRequest $request): JsonResponse
    {
        return $this->success($this->groupService->stats($request->all()));
    }

    public function options(): JsonResponse
    {
        $groups = SchedulingEmployeeGroup::where('status', true)
            ->get(['id', 'name']);

        return $this->success($groups);
    }

    /**
     * Danh sách các nhóm nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, mô tả.
     * @queryParam status boolean Lọc theo trạng thái hoạt động (true/false).
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 20
     */
    public function index(FilterRequest $request): JsonResponse
    {
        return $this->successCollection(
            new SchedulingEmployeeGroupCollection(
                $this->groupService->index($request->all(), (int)($request->limit ?? 20))
            )
        );
    }

    /**
     * Chi tiết nhóm nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm. Example: 1
     */
    public function show(SchedulingEmployeeGroup $schedulingEmployeeGroup): JsonResponse
    {
        return $this->successResource(new SchedulingEmployeeGroupResource($this->groupService->show($schedulingEmployeeGroup)));
    }

    /**
     * Tạo nhóm nhân viên lịch công tác mới.
     *
     * @bodyParam name string required Tên nhóm. Example: Nhóm A
     * @bodyParam description string Mô tả nhóm. Example: Nhóm thường trực
     * @bodyParam status boolean Trạng thái hoạt động. Example: true
     * @bodyParam employee_ids array Danh sách ID nhân viên thành viên ban đầu. Example: [1, 2]
     */
    public function store(StoreSchedulingEmployeeGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->store($request->validated());
        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Tạo nhóm nhân viên thành công!', 201);
    }

    /**
     * Cập nhật thông tin nhóm nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm. Example: 1
     * @bodyParam name string required Tên nhóm. Example: Nhóm A
     * @bodyParam description string Mô tả nhóm. Example: Nhóm thường trực
     * @bodyParam status boolean Trạng thái hoạt động. Example: true
     * @bodyParam employee_ids array Danh sách ID nhân viên thành viên mới. Example: [1, 2]
     */
    public function update(UpdateSchedulingEmployeeGroupRequest $request, SchedulingEmployeeGroup $schedulingEmployeeGroup): JsonResponse
    {
        $group = $this->groupService->update($schedulingEmployeeGroup, $request->validated());
        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Cập nhật nhóm nhân viên thành công!');
    }

    /**
     * Xóa nhóm nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm. Example: 1
     */
    public function destroy(SchedulingEmployeeGroup $schedulingEmployeeGroup): JsonResponse
    {
        $this->groupService->destroy($schedulingEmployeeGroup);
        return $this->success(null, 'Xóa nhóm nhân viên thành công!');
    }

    /**
     * Xóa hàng loạt nhóm nhân viên.
     *
     * @bodyParam ids array required Danh sách ID nhóm cần xóa. Example: [1, 2]
     */
    public function bulkDestroy(BulkDestroySchedulingEmployeeGroupRequest $request): JsonResponse
    {
        $this->groupService->bulkDestroy($request->input('ids'));
        return $this->success(null, 'Xóa nhiều nhóm nhân viên thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhóm nhân viên.
     *
     * @bodyParam ids array required Danh sách ID nhóm cần cập nhật. Example: [1, 2]
     * @bodyParam status boolean required Trạng thái mới. Example: true
     */
    public function bulkUpdateStatus(BulkUpdateStatusSchedulingEmployeeGroupRequest $request): JsonResponse
    {
        $this->groupService->bulkUpdateStatus($request->input('ids'), $request->input('status'));
        return $this->success(null, 'Cập nhật trạng thái nhiều nhóm nhân viên thành công!');
    }

    /**
     * Thay đổi trạng thái nhóm nhân viên nhanh.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm. Example: 1
     * @bodyParam status boolean required Trạng thái mới. Example: true
     */
    public function changeStatus(ChangeStatusSchedulingEmployeeGroupRequest $request, SchedulingEmployeeGroup $schedulingEmployeeGroup): JsonResponse
    {
        $group = $this->groupService->changeStatus($schedulingEmployeeGroup, $request->input('status'));
        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Cập nhật trạng thái nhóm nhân viên thành công!');
    }

    /**
     * Đồng bộ thành viên của nhóm nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm. Example: 1
     * @bodyParam employee_ids array required Danh sách ID nhân viên. Example: [1, 2]
     */
    public function syncMembers(SyncMembersSchedulingEmployeeGroupRequest $request, SchedulingEmployeeGroup $schedulingEmployeeGroup): JsonResponse
    {
        $this->groupService->syncMembers($schedulingEmployeeGroup, $request->input('employee_ids'));
        return $this->success(null, 'Đồng bộ thành viên nhóm thành công!');
    }
}
