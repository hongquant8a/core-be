<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use App\Modules\TaskAssignment\Requests\BulkDestroyEmployeeRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusEmployeeRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusEmployeeRequest;
use App\Modules\TaskAssignment\Requests\ImportLookupRequest;
use App\Modules\TaskAssignment\Requests\StoreEmployeeRequest;
use App\Modules\TaskAssignment\Requests\UpdateEmployeeRequest;
use App\Modules\TaskAssignment\Resources\EmployeeCollection;
use App\Modules\TaskAssignment\Resources\EmployeeResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentEmployeeService;

/**
 * @group TaskAssignment - Nhân viên giao việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý danh sách nhân viên module giao việc: chỉ user có trong bảng này mới được gán vào phòng ban và giao công việc.
 */
class TaskAssignmentEmployeeController extends Controller
{
    public function __construct(private TaskAssignmentEmployeeService $employeeService) {}

    /**
     * Danh sách nhân viên cho dropdown
     *
     * Trả về danh sách rút gọn (id, user_id, name, email) — dùng để FE hiển thị dropdown chọn nhân viên khi gán vào phòng ban.
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive. Example: active
     * @queryParam department_id integer Lọc nhân viên thuộc phòng ban. Example: 1
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
     * Thống kê nhân viên
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam department_id integer Lọc nhân viên thuộc phòng ban.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 30, "active": 28, "inactive": 2}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->employeeService->stats($request->all()));
    }

    /**
     * Danh sách nhân viên giao việc
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam department_id integer Lọc nhân viên thuộc phòng ban. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, status, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\EmployeeCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentEmployee paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->employeeService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new EmployeeCollection($items));
    }

    /**
     * Chi tiết nhân viên giao việc
     *
     * @urlParam taskAssignmentEmployee integer required ID nhân viên. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\EmployeeResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentEmployee
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentEmployee $taskAssignmentEmployee)
    {
        return $this->successResource(new EmployeeResource($this->employeeService->show($taskAssignmentEmployee)));
    }

    /**
     * Đăng ký nhân viên giao việc
     *
     * Thêm một user (từ danh sách users tổng) vào module Task. Sau khi đăng ký, user mới có thể được gán vào phòng ban và giao công việc.
     *
     * @bodyParam user_id integer required ID user trong bảng users tổng. Example: 5
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     * @bodyParam note string Ghi chú nội bộ. Example: Bổ sung nhân sự phòng nghiệp vụ.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\EmployeeResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentEmployee
     *
     * @apiResourceAdditional success=true message="Đăng ký nhân viên thành công!"
     */
    public function store(StoreEmployeeRequest $request)
    {
        $item = $this->employeeService->store($request->validated());

        return $this->successResource(new EmployeeResource($item), 'Đăng ký nhân viên thành công!', 201);
    }

    /**
     * Cập nhật nhân viên giao việc
     *
     * @urlParam taskAssignmentEmployee integer required ID nhân viên. Example: 1
     *
     * @bodyParam status string Trạng thái: active, inactive.
     * @bodyParam note string Ghi chú nội bộ.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\EmployeeResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentEmployee
     *
     * @apiResourceAdditional success=true message="Cập nhật nhân viên thành công!"
     */
    public function update(UpdateEmployeeRequest $request, TaskAssignmentEmployee $taskAssignmentEmployee)
    {
        $item = $this->employeeService->update($taskAssignmentEmployee, $request->validated());

        return $this->successResource(new EmployeeResource($item), 'Cập nhật nhân viên thành công!');
    }

    /**
     * Xóa nhân viên giao việc
     *
     * Soft block: trả 409 nếu nhân viên đang thuộc phòng ban hoặc còn công việc. Phải remove khỏi dept/task trước khi xóa.
     *
     * @urlParam taskAssignmentEmployee integer required ID nhân viên. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa nhân viên thành công!"}
     * @response 409 {"success": false, "message": "Không thể xóa nhân viên đang thuộc phòng ban hoặc còn công việc: ..."}
     */
    public function destroy(TaskAssignmentEmployee $taskAssignmentEmployee)
    {
        $this->employeeService->destroy($taskAssignmentEmployee);

        return $this->success(null, 'Xóa nhân viên thành công!');
    }

    /**
     * Xóa hàng loạt nhân viên giao việc
     *
     * Soft block 409 nếu bất kỳ nhân viên nào còn ràng buộc — không xóa partial, atomic toàn bộ.
     *
     * @bodyParam ids array required Danh sách ID nhân viên. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt nhân viên thành công!"}
     */
    public function bulkDestroy(BulkDestroyEmployeeRequest $request)
    {
        $this->employeeService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt nhân viên thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhân viên
     *
     * @bodyParam ids array required Danh sách ID nhân viên. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: inactive
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusEmployeeRequest $request)
    {
        $this->employeeService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái nhân viên
     *
     * @urlParam taskAssignmentEmployee integer required ID nhân viên. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\EmployeeResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentEmployee
     *
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusEmployeeRequest $request, TaskAssignmentEmployee $taskAssignmentEmployee)
    {
        $item = $this->employeeService->changeStatus($taskAssignmentEmployee, $request->status);

        return $this->successResource(new EmployeeResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel nhân viên giao việc
     *
     * Xuất ra các trường: id, user_id, name, email, user_name, status, note, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Tìm theo tên, email, user_name.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam department_id integer Lọc nhân viên thuộc phòng ban.
     */
    public function export(FilterRequest $request)
    {
        return $this->employeeService->export($request->all());
    }

    /**
     * Import nhân viên giao việc
     *
     * Cột bắt buộc: user_id. Cột không bắt buộc: status (mặc định "active"), note.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import nhân viên thành công."}
     */
    public function import(ImportLookupRequest $request)
    {
        $this->employeeService->import($request->file('file'));

        return $this->success(null, 'Import nhân viên thành công.');
    }

    /**
     * Tải mẫu import nhân viên
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\TaskAssignment\Imports\EmployeeImport::TEMPLATE_LABELS),
            'import-employees-template.xlsx'
        );
    }
}
