<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Requests\BulkDestroyDepartmentRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusDepartmentRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusDepartmentRequest;
use App\Modules\TaskAssignment\Requests\ImportLookupRequest;
use App\Modules\TaskAssignment\Requests\StoreDepartmentRequest;
use App\Modules\TaskAssignment\Requests\UpdateDepartmentRequest;
use App\Modules\TaskAssignment\Resources\DepartmentCollection;
use App\Modules\TaskAssignment\Resources\DepartmentResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentDepartmentService;

/**
 * @group TaskAssignment - Phòng ban giao việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý phòng ban giao việc: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt, xuất/nhập và đổi trạng thái.
 */
class TaskAssignmentDepartmentController extends Controller
{
    public function __construct(private TaskAssignmentDepartmentService $departmentService) {}

    /**
     * Danh sách phòng ban giao việc công khai
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\DepartmentCollection
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     * @apiResourceAdditional success=true
     */
    public function public(FilterRequest $request)
    {
        $items = $this->departmentService->publicList($request->all());

        return $this->successCollection(new DepartmentCollection($items));
    }

    /**
     * Danh sách phòng ban giao việc cho dropdown
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\Core\Resources\PublicOptionResource
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     * @apiResourceAdditional success=true
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->departmentService->publicOptions($request->all());

        return $this->successCollection(\App\Modules\Core\Resources\PublicOptionResource::collection($items));
    }

    /**
     * Thống kê phòng ban giao việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 10, "active": 8, "inactive": 2}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->departmentService->stats($request->all()));
    }

    /**
     * Danh sách phòng ban giao việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\DepartmentCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->departmentService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new DepartmentCollection($items));
    }

    /**
     * Chi tiết phòng ban giao việc
     *
     * @urlParam taskAssignmentDepartment integer required ID phòng ban giao việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DepartmentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        return $this->successResource(new DepartmentResource($this->departmentService->show($taskAssignmentDepartment)));
    }

    /**
     * Tạo phòng ban giao việc
     *
     * @bodyParam name string required Tên phòng ban. Example: Phòng Kế hoạch
     * @bodyParam description string Mô tả.
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DepartmentResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     *
     * @apiResourceAdditional success=true message="Tạo phòng ban giao việc thành công!"
     */
    public function store(StoreDepartmentRequest $request)
    {
        $item = $this->departmentService->store($request->validated());

        return $this->successResource(new DepartmentResource($item), 'Tạo phòng ban giao việc thành công!', 201);
    }

    /**
     * Cập nhật phòng ban giao việc
     *
     * @urlParam taskAssignmentDepartment integer required ID phòng ban giao việc. Example: 1
     *
     * @bodyParam name string Tên phòng ban.
     * @bodyParam description string Mô tả.
     * @bodyParam status string Trạng thái: active, inactive.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DepartmentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     *
     * @apiResourceAdditional success=true message="Cập nhật phòng ban giao việc thành công!"
     */
    public function update(UpdateDepartmentRequest $request, TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        $item = $this->departmentService->update($taskAssignmentDepartment, $request->validated());

        return $this->successResource(new DepartmentResource($item), 'Cập nhật phòng ban giao việc thành công!');
    }

    /**
     * Xóa phòng ban giao việc
     *
     * @urlParam taskAssignmentDepartment integer required ID phòng ban giao việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa phòng ban giao việc thành công!"}
     */
    public function destroy(TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        $this->departmentService->destroy($taskAssignmentDepartment);

        return $this->success(null, 'Xóa phòng ban giao việc thành công!');
    }

    /**
     * Xóa hàng loạt phòng ban giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyDepartmentRequest $request)
    {
        $this->departmentService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt phòng ban giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: inactive
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusDepartmentRequest $request)
    {
        $this->departmentService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái phòng ban giao việc
     *
     * @urlParam taskAssignmentDepartment integer required ID phòng ban giao việc. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DepartmentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDepartment
     *
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusDepartmentRequest $request, TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        $item = $this->departmentService->changeStatus($taskAssignmentDepartment, $request->status);

        return $this->successResource(new DepartmentResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel phòng ban giao việc
     *
     * Xuất ra các trường: id, name, description, status, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     */
    public function export(FilterRequest $request)
    {
        return $this->departmentService->export($request->all());
    }

    /**
     * Import phòng ban giao việc
     *
     * Cột bắt buộc: name. Cột không bắt buộc: description, status (mặc định "active").
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import phòng ban giao việc thành công."}
     */
    public function import(ImportLookupRequest $request)
    {
        $this->departmentService->import($request->file('file'));

        return $this->success(null, 'Import phòng ban giao việc thành công.');
    }

    /**
     * Tải mẫu import phòng ban giao việc
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(['code', 'name', 'description', 'status', 'sort_order']),
            'import-departments-template.xlsx'
        );
    }
}
