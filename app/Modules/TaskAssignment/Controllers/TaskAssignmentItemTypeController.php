<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Requests\BulkDestroyLookupRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusLookupRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusLookupRequest;
use App\Modules\TaskAssignment\Requests\ImportLookupRequest;
use App\Modules\TaskAssignment\Requests\StoreLookupRequest;
use App\Modules\TaskAssignment\Requests\UpdateLookupRequest;
use App\Modules\TaskAssignment\Resources\LookupCollection;
use App\Modules\TaskAssignment\Resources\LookupResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentLookupService;

/**
 * @group TaskAssignment - Loại công việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý danh mục loại công việc: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt, xuất/nhập và đổi trạng thái.
 */
class TaskAssignmentItemTypeController extends Controller
{
    public function __construct(private TaskAssignmentLookupService $lookupService) {}

    /**
     * Danh sách loại công việc công khai
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\LookupCollection
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     * @apiResourceAdditional success=true
     */
    public function public(FilterRequest $request)
    {
        $items = $this->lookupService->publicList(TaskAssignmentItemType::class, $request->all());

        return $this->successCollection(new LookupCollection($items));
    }

    /**
     * Danh sách loại công việc cho dropdown
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\Core\Resources\PublicOptionResource
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     * @apiResourceAdditional success=true
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->lookupService->publicOptions(TaskAssignmentItemType::class, $request->all());

        return $this->successCollection(\App\Modules\Core\Resources\PublicOptionResource::collection($items));
    }

    /**
     * Thống kê loại công việc
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
        return $this->success($this->lookupService->stats(TaskAssignmentItemType::class, $request->all()));
    }

    /**
     * Danh sách loại công việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\LookupCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->lookupService->index(TaskAssignmentItemType::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new LookupCollection($items));
    }

    /**
     * Chi tiết loại công việc
     *
     * @urlParam taskAssignmentItemType integer required ID loại công việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentItemType $taskAssignmentItemType)
    {
        return $this->successResource(new LookupResource($this->lookupService->show($taskAssignmentItemType)));
    }

    /**
     * Tạo loại công việc
     *
     * @bodyParam name string required Tên loại công việc. Example: Công việc chuyên môn
     * @bodyParam description string Mô tả.
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     *
     * @apiResourceAdditional success=true message="Tạo loại công việc thành công!"
     */
    public function store(StoreLookupRequest $request)
    {
        $item = $this->lookupService->store(TaskAssignmentItemType::class, $request->validated());

        return $this->successResource(new LookupResource($item), 'Tạo loại công việc thành công!', 201);
    }

    /**
     * Cập nhật loại công việc
     *
     * @urlParam taskAssignmentItemType integer required ID loại công việc. Example: 1
     *
     * @bodyParam name string Tên loại công việc.
     * @bodyParam description string Mô tả.
     * @bodyParam status string Trạng thái: active, inactive.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     *
     * @apiResourceAdditional success=true message="Cập nhật loại công việc thành công!"
     */
    public function update(UpdateLookupRequest $request, TaskAssignmentItemType $taskAssignmentItemType)
    {
        $item = $this->lookupService->update($taskAssignmentItemType, $request->validated());

        return $this->successResource(new LookupResource($item), 'Cập nhật loại công việc thành công!');
    }

    /**
     * Xóa loại công việc
     *
     * @urlParam taskAssignmentItemType integer required ID loại công việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa loại công việc thành công!"}
     */
    public function destroy(TaskAssignmentItemType $taskAssignmentItemType)
    {
        $this->lookupService->destroy($taskAssignmentItemType);

        return $this->success(null, 'Xóa loại công việc thành công!');
    }

    /**
     * Xóa hàng loạt loại công việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyLookupRequest $request)
    {
        $this->lookupService->bulkDestroy(TaskAssignmentItemType::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt loại công việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: inactive
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusLookupRequest $request)
    {
        $this->lookupService->bulkUpdateStatus(TaskAssignmentItemType::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái loại công việc
     *
     * @urlParam taskAssignmentItemType integer required ID loại công việc. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemType
     *
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusLookupRequest $request, TaskAssignmentItemType $taskAssignmentItemType)
    {
        $item = $this->lookupService->changeStatus($taskAssignmentItemType, $request->status);

        return $this->successResource(new LookupResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel loại công việc
     *
     * Xuất ra các trường: id, name, description, status, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     */
    public function export(FilterRequest $request)
    {
        return $this->lookupService->export(TaskAssignmentItemType::class, $request->all(), 'task-assignment-item-types.xlsx');
    }

    /**
     * Import loại công việc
     *
     * Cột bắt buộc: name. Cột không bắt buộc: description, status (mặc định "active").
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import loại công việc thành công."}
     */
    public function import(ImportLookupRequest $request)
    {
        $this->lookupService->import(TaskAssignmentItemType::class, $request->file('file'));

        return $this->success(null, 'Import loại công việc thành công.');
    }

    /**
     * Tải mẫu import loại công việc
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\TaskAssignment\Imports\LookupImport::TEMPLATE_LABELS),
            'import-item-types-template.xlsx'
        );
    }
}
