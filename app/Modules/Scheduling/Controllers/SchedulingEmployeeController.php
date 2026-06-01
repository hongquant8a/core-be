<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Requests\{
    StoreSchedulingEmployeeRequest, UpdateSchedulingEmployeeRequest,
    BulkDestroySchedulingEmployeeRequest, BulkUpdateStatusSchedulingEmployeeRequest,
    ChangeStatusSchedulingEmployeeRequest, ImportSchedulingEmployeeRequest,
    SyncGroupsSchedulingEmployeeRequest
};
use App\Modules\Scheduling\Resources\{SchedulingEmployeeCollection, SchedulingEmployeeResource};
use App\Modules\Scheduling\Services\SchedulingEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Scheduling - Nhân viên lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý danh sách nhân viên module lịch công tác: chỉ user có trong bảng này mới được giao nhiệm vụ chủ trì hoặc gán vào lịch công tác.
 */
class SchedulingEmployeeController extends Controller
{
    public function __construct(private SchedulingEmployeeService $employeeService) {}

    /**
     * Thống kê nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, email.
     * @queryParam status boolean Lọc theo trạng thái hoạt động (true/false).
     */
    public function stats(FilterRequest $request): JsonResponse
    {
        return $this->success($this->employeeService->stats($request->all()));
    }

    /**
     * Danh sách nhân viên cho dropdown.
     *
     * Trả về danh sách rút gọn (id, user_id, name) — dùng để FE hiển thị dropdown chọn chủ trì khi tạo lịch công tác.
     */
    public function options(): JsonResponse
    {
        $employees = SchedulingEmployee::with('user')
            ->where('status', true)
            ->get();

        $res = $employees->map(fn ($emp) => [
            'id' => $emp->id,
            'user_id' => $emp->user_id,
            'name' => $emp->user?->name,
        ]);

        return $this->success($res);
    }

    /**
     * Danh sách nhân viên lịch công tác.
     *
     * @queryParam search string Tìm theo tên, email.
     * @queryParam status boolean Lọc theo trạng thái hoạt động (true/false).
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 20
     */
    public function index(FilterRequest $request): JsonResponse
    {
        return $this->successCollection(
            new SchedulingEmployeeCollection(
                $this->employeeService->index($request->all(), (int)($request->limit ?? 20))
            )
        );
    }

    /**
     * Chi tiết nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function show(SchedulingEmployee $schedulingEmployee): JsonResponse
    {
        return $this->successResource(new SchedulingEmployeeResource($this->employeeService->show($schedulingEmployee)));
    }

    /**
     * Thêm nhân viên vào module lịch công tác.
     *
     * @bodyParam user_id integer required ID user (users table). Example: 3
     * @bodyParam status boolean Trạng thái hoạt động. Example: true
     * @bodyParam note string Ghi chú thêm. Example: Cán bộ phòng kỹ thuật
     */
    public function store(StoreSchedulingEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->store($request->validated());
        return $this->successResource(new SchedulingEmployeeResource($employee), 'Thêm nhân viên thành công!', 201);
    }

    /**
     * Cập nhật thông tin nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     * @bodyParam status boolean Trạng thái hoạt động. Example: true
     * @bodyParam note string Ghi chú thêm. Example: Cán bộ phòng kỹ thuật
     */
    public function update(UpdateSchedulingEmployeeRequest $request, SchedulingEmployee $schedulingEmployee): JsonResponse
    {
        $employee = $this->employeeService->update($schedulingEmployee, $request->validated());
        return $this->successResource(new SchedulingEmployeeResource($employee), 'Cập nhật nhân viên thành công!');
    }

    /**
     * Xóa nhân viên khỏi module lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     */
    public function destroy(SchedulingEmployee $schedulingEmployee): JsonResponse
    {
        $this->employeeService->destroy($schedulingEmployee);
        return $this->success(null, 'Xóa nhân viên thành công!');
    }

    /**
     * Xóa hàng loạt nhân viên khỏi module lịch công tác.
     *
     * @bodyParam ids array required Danh sách ID nhân viên cần xóa. Example: [1, 2]
     */
    public function bulkDestroy(BulkDestroySchedulingEmployeeRequest $request): JsonResponse
    {
        $this->employeeService->bulkDestroy($request->input('ids'));
        return $this->success(null, 'Xóa nhiều nhân viên thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhân viên.
     *
     * @bodyParam ids array required Danh sách ID nhân viên cần cập nhật. Example: [1, 2]
     * @bodyParam status boolean required Trạng thái mới. Example: true
     */
    public function bulkUpdateStatus(BulkUpdateStatusSchedulingEmployeeRequest $request): JsonResponse
    {
        $this->employeeService->bulkUpdateStatus($request->input('ids'), $request->input('status'));
        return $this->success(null, 'Cập nhật trạng thái nhiều nhân viên thành công!');
    }

    /**
     * Thay đổi trạng thái nhân viên lịch công tác nhanh.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     * @bodyParam status boolean required Trạng thái mới. Example: true
     */
    public function changeStatus(ChangeStatusSchedulingEmployeeRequest $request, SchedulingEmployee $schedulingEmployee): JsonResponse
    {
        $employee = $this->employeeService->changeStatus($schedulingEmployee, $request->input('status'));
        return $this->successResource(new SchedulingEmployeeResource($employee), 'Cập nhật trạng thái nhân viên thành công!');
    }

    /**
     * Xuất danh sách nhân viên lịch công tác ra file Excel.
     */
    public function export(FilterRequest $request)
    {
        return $this->employeeService->export($request->all());
    }

    /**
     * Nhập danh sách nhân viên lịch công tác từ file Excel.
     *
     * @bodyParam file file required File Excel chứa danh sách nhân viên (.xlsx, .xls, .csv).
     */
    public function import(ImportSchedulingEmployeeRequest $request): JsonResponse
    {
        $res = $this->employeeService->import($request->file('file'));
        return $this->success($res, 'Nhập danh sách nhân viên thành công!');
    }

    /**
     * Đồng bộ danh sách nhóm của nhân viên lịch công tác.
     *
     * @urlParam schedulingEmployee integer required ID nhân viên. Example: 1
     * @bodyParam group_ids array required Danh sách ID nhóm. Example: [1, 2]
     */
    public function syncGroups(SyncGroupsSchedulingEmployeeRequest $request, SchedulingEmployee $schedulingEmployee): JsonResponse
    {
        $this->employeeService->syncGroups($schedulingEmployee, $request->input('group_ids'));
        return $this->success(null, 'Đồng bộ nhóm nhân viên thành công!');
    }
}
