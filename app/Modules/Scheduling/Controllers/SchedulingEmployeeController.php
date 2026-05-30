<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Requests\BulkDestroySchedulingEmployeeRequest;
use App\Modules\Scheduling\Requests\BulkUpdateStatusSchedulingEmployeeRequest;
use App\Modules\Scheduling\Requests\ChangeStatusSchedulingEmployeeRequest;
use App\Modules\Scheduling\Requests\StoreSchedulingEmployeeRequest;
use App\Modules\Scheduling\Requests\UpdateSchedulingEmployeeRequest;
use App\Modules\Scheduling\Resources\SchedulingEmployeeCollection;
use App\Modules\Scheduling\Resources\SchedulingEmployeeResource;
use App\Modules\Scheduling\Services\SchedulingEmployeeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Nhân viên lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý danh sách nhân viên module lịch công tác: chỉ user có trong bảng này mới được giao nhiệm vụ chủ trì hoặc gán vào lịch công tác.
 */
class SchedulingEmployeeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SchedulingEmployeeService $employeeService) {}

    /**
     * Danh sách nhân viên cho dropdown.
     *
     * Trả về danh sách rút gọn (id, user_id, name, email) — dùng để FE hiển thị dropdown chọn chủ trì khi tạo lịch công tác.
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive. Example: active
     * @queryParam sort_by string Sắp xếp theo: id, status, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     */
    public function options(FilterRequest $request)
    {
        $items = $this->employeeService->publicOptions($request->all());

        return $this->success($items->map(fn ($emp) => [
            'id' => $emp->id,
            'user_id' => $emp->user_id,
            'name' => $emp->user?->name,
            'email' => $emp->user?->email,
            'user_name' => $emp->user?->user_name,
            'status' => $emp->status,
        ]));
    }

    /**
     * Thống kê nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 30, "active": 28, "inactive": 2}}
     */
    public function stats(FilterRequest $request)
    {
        $this->authorize('viewAny', SchedulingEmployee::class);

        return $this->success($this->employeeService->stats($request->all()));
    }

    /**
     * Danh sách nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, status, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', SchedulingEmployee::class);

        $items = $this->employeeService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new SchedulingEmployeeCollection($items));
    }

    /**
     * Chi tiết nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function show(SchedulingEmployee $schedulingEmployee)
    {
        $this->authorize('view', $schedulingEmployee);

        $loaded = $this->employeeService->show($schedulingEmployee);

        return $this->successResource(new SchedulingEmployeeResource($loaded));
    }

    /**
     * Thêm nhân viên vào module lịch công tác.
     */
    public function store(StoreSchedulingEmployeeRequest $request)
    {
        $this->authorize('create', SchedulingEmployee::class);

        $validated = $request->validated();
        $validated['organization_id'] = getPermissionsTeamId();

        $employee = $this->employeeService->store($validated);

        return $this->successResource(new SchedulingEmployeeResource($employee), 'Thêm nhân viên thành công!', 201);
    }

    /**
     * Cập nhật thông tin nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function update(UpdateSchedulingEmployeeRequest $request, SchedulingEmployee $schedulingEmployee)
    {
        $this->authorize('update', $schedulingEmployee);

        $employee = $this->employeeService->update($schedulingEmployee, $request->validated());

        return $this->successResource(new SchedulingEmployeeResource($employee), 'Cập nhật nhân viên thành công!');
    }

    /**
     * Xóa nhân viên khỏi module lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function destroy(SchedulingEmployee $schedulingEmployee)
    {
        $this->authorize('delete', $schedulingEmployee);

        $this->employeeService->destroy($schedulingEmployee);

        return $this->success(null, 'Xóa nhân viên thành công!');
    }

    /**
     * Thay đổi trạng thái nhân viên.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function changeStatus(ChangeStatusSchedulingEmployeeRequest $request, SchedulingEmployee $schedulingEmployee)
    {
        $this->authorize('update', $schedulingEmployee);

        $employee = $this->employeeService->changeStatus($schedulingEmployee, $request->status);

        return $this->successResource(new SchedulingEmployeeResource($employee), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhân viên.
     */
    public function bulkUpdateStatus(BulkUpdateStatusSchedulingEmployeeRequest $request)
    {
        $this->authorize('bulkUpdateStatus', SchedulingEmployee::class);

        $this->employeeService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Xóa hàng loạt nhân viên.
     */
    public function bulkDestroy(BulkDestroySchedulingEmployeeRequest $request)
    {
        $this->authorize('bulkDestroy', SchedulingEmployee::class);

        $this->employeeService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt nhân viên thành công!');
    }
}
