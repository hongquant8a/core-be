<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use App\Modules\Scheduling\Requests\BulkDestroySchedulingEmployeeGroupRequest;
use App\Modules\Scheduling\Requests\BulkUpdateStatusSchedulingEmployeeGroupRequest;
use App\Modules\Scheduling\Requests\ChangeStatusSchedulingEmployeeGroupRequest;
use App\Modules\Scheduling\Requests\StoreSchedulingEmployeeGroupRequest;
use App\Modules\Scheduling\Requests\UpdateSchedulingEmployeeGroupRequest;
use App\Modules\Scheduling\Resources\SchedulingEmployeeGroupCollection;
use App\Modules\Scheduling\Resources\SchedulingEmployeeGroupResource;
use App\Modules\Scheduling\Services\SchedulingEmployeeGroupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Nhóm nhân viên lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý danh sách các nhóm nhân viên lịch công tác.
 */
class SchedulingEmployeeGroupController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SchedulingEmployeeGroupService $groupService) {}

    /**
     * Danh sách nhóm nhân viên cho dropdown.
     *
     * @queryParam search string Tìm theo tên nhóm.
     */
    public function options(FilterRequest $request)
    {
        $items = $this->groupService->options($request->all());

        return $this->success($items->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
        ]));
    }

    /**
     * Thống kê nhóm nhân viên.
     *
     * @queryParam search string Tìm theo tên nhóm.
     * @response 200 {"success": true, "data": {"total": 5, "active": 4, "inactive": 1}}
     */
    public function stats(FilterRequest $request)
    {
        $this->authorize('viewAny', SchedulingEmployeeGroup::class);

        return $this->success($this->groupService->stats($request->all()));
    }

    /**
     * Danh sách các nhóm nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, mô tả.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam sort_by string Sắp xếp theo: id, name, status, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', SchedulingEmployeeGroup::class);

        $items = $this->groupService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new SchedulingEmployeeGroupCollection($items));
    }

    /**
     * Chi tiết nhóm nhân viên.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm nhân viên. Example: 1
     */
    public function show(SchedulingEmployeeGroup $schedulingEmployeeGroup)
    {
        $this->authorize('view', $schedulingEmployeeGroup);

        $loaded = $this->groupService->show($schedulingEmployeeGroup);

        return $this->successResource(new SchedulingEmployeeGroupResource($loaded));
    }

    /**
     * Tạo nhóm nhân viên lịch công tác mới.
     */
    public function store(StoreSchedulingEmployeeGroupRequest $request)
    {
        $this->authorize('create', SchedulingEmployeeGroup::class);

        $validated = $request->validated();
        $validated['organization_id'] = getPermissionsTeamId();

        $group = $this->groupService->store($validated);

        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Tạo nhóm nhân viên thành công!', 201);
    }

    /**
     * Cập nhật thông tin nhóm nhân viên.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm nhân viên. Example: 1
     */
    public function update(UpdateSchedulingEmployeeGroupRequest $request, SchedulingEmployeeGroup $schedulingEmployeeGroup)
    {
        $this->authorize('update', $schedulingEmployeeGroup);

        $group = $this->groupService->update($schedulingEmployeeGroup, $request->validated());

        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Cập nhật nhóm nhân viên thành công!');
    }

    /**
     * Xóa nhóm nhân viên.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm nhân viên. Example: 1
     */
    public function destroy(SchedulingEmployeeGroup $schedulingEmployeeGroup)
    {
        $this->authorize('delete', $schedulingEmployeeGroup);

        $this->groupService->destroy($schedulingEmployeeGroup);

        return $this->success(null, 'Xóa nhóm nhân viên thành công!');
    }

    /**
     * Thay đổi trạng thái nhóm nhân viên nhanh.
     *
     * @urlParam schedulingEmployeeGroup integer required ID nhóm nhân viên. Example: 1
     */
    public function changeStatus(ChangeStatusSchedulingEmployeeGroupRequest $request, SchedulingEmployeeGroup $schedulingEmployeeGroup)
    {
        $this->authorize('update', $schedulingEmployeeGroup);

        $group = $this->groupService->changeStatus($schedulingEmployeeGroup, $request->status);

        return $this->successResource(new SchedulingEmployeeGroupResource($group), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Thay đổi trạng thái hàng loạt nhóm nhân viên.
     */
    public function bulkUpdateStatus(BulkUpdateStatusSchedulingEmployeeGroupRequest $request)
    {
        $this->authorize('bulkUpdateStatus', SchedulingEmployeeGroup::class);

        $this->groupService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Xóa hàng loạt nhóm nhân viên.
     */
    public function bulkDestroy(BulkDestroySchedulingEmployeeGroupRequest $request)
    {
        $this->authorize('bulkDestroy', SchedulingEmployeeGroup::class);

        $this->groupService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt nhóm nhân viên thành công!');
    }
}
