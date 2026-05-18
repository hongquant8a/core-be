<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
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
 * @group TaskAssignment - Loại văn bản giao việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý danh mục loại văn bản giao việc: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt, xuất/nhập và đổi trạng thái.
 */
class TaskAssignmentTypeController extends Controller
{
    public function __construct(private TaskAssignmentLookupService $lookupService) {}

    /**
     * Danh sách loại văn bản giao việc công khai
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\LookupCollection
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     * @apiResourceAdditional success=true
     */
    public function public(FilterRequest $request)
    {
        $items = $this->lookupService->publicList(TaskAssignmentType::class, $request->all());

        return $this->successCollection(new LookupCollection($items));
    }

    /**
     * Danh sách loại văn bản giao việc cho dropdown
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: name
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     *
     * @apiResourceCollection App\Modules\Core\Resources\PublicOptionResource
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     * @apiResourceAdditional success=true
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->lookupService->publicOptions(TaskAssignmentType::class, $request->all());

        return $this->successCollection(\App\Modules\Core\Resources\PublicOptionResource::collection($items));
    }

    /**
     * Thống kê loại văn bản giao việc
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
        return $this->success($this->lookupService->stats(TaskAssignmentType::class, $request->all()));
    }

    /**
     * Danh sách loại văn bản giao việc
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
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->lookupService->index(TaskAssignmentType::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new LookupCollection($items));
    }

    /**
     * Chi tiết loại văn bản giao việc
     *
     * @urlParam taskAssignmentType integer required ID loại văn bản giao việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentType $taskAssignmentType)
    {
        return $this->successResource(new LookupResource($this->lookupService->show($taskAssignmentType)));
    }

    /**
     * Tạo loại văn bản giao việc
     *
     * @bodyParam name string required Tên loại văn bản giao việc. Example: Quyết định giao việc
     * @bodyParam description string Mô tả.
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     *
     * @apiResourceAdditional success=true message="Tạo loại văn bản giao việc thành công!"
     */
    public function store(StoreLookupRequest $request)
    {
        $item = $this->lookupService->store(TaskAssignmentType::class, $request->validated());

        return $this->successResource(new LookupResource($item), 'Tạo loại văn bản giao việc thành công!', 201);
    }

    /**
     * Cập nhật loại văn bản giao việc
     *
     * @urlParam taskAssignmentType integer required ID loại văn bản giao việc. Example: 1
     *
     * @bodyParam name string Tên loại văn bản giao việc.
     * @bodyParam description string Mô tả.
     * @bodyParam status string Trạng thái: active, inactive.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     *
     * @apiResourceAdditional success=true message="Cập nhật loại văn bản giao việc thành công!"
     */
    public function update(UpdateLookupRequest $request, TaskAssignmentType $taskAssignmentType)
    {
        $item = $this->lookupService->update($taskAssignmentType, $request->validated());

        return $this->successResource(new LookupResource($item), 'Cập nhật loại văn bản giao việc thành công!');
    }

    /**
     * Xóa loại văn bản giao việc
     *
     * @urlParam taskAssignmentType integer required ID loại văn bản giao việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa loại văn bản giao việc thành công!"}
     */
    public function destroy(TaskAssignmentType $taskAssignmentType)
    {
        $this->lookupService->destroy($taskAssignmentType);

        return $this->success(null, 'Xóa loại văn bản giao việc thành công!');
    }

    /**
     * Xóa hàng loạt loại văn bản giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyLookupRequest $request)
    {
        $this->lookupService->bulkDestroy(TaskAssignmentType::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt loại văn bản giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: inactive
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusLookupRequest $request)
    {
        $this->lookupService->bulkUpdateStatus(TaskAssignmentType::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái loại văn bản giao việc
     *
     * @urlParam taskAssignmentType integer required ID loại văn bản giao việc. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: active
     *
     * @apiResource App\Modules\TaskAssignment\Resources\LookupResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentType
     *
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusLookupRequest $request, TaskAssignmentType $taskAssignmentType)
    {
        $item = $this->lookupService->changeStatus($taskAssignmentType, $request->status);

        return $this->successResource(new LookupResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel loại văn bản giao việc
     *
     * Xuất ra các trường: id, name, description, status, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     */
    public function export(FilterRequest $request)
    {
        return $this->lookupService->export(TaskAssignmentType::class, $request->all(), ExportFilename::make('loai-van-ban-giao-viec'));
    }

    /**
     * Import loại văn bản giao việc
     *
     * Cột bắt buộc: name. Cột không bắt buộc: description, status (mặc định "active").
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import loại văn bản giao việc thành công."}
     */
    public function import(ImportLookupRequest $request)
    {
        $this->lookupService->import(TaskAssignmentType::class, $request->file('file'));

        return $this->success(null, 'Import loại văn bản giao việc thành công.');
    }

    /**
     * Tải mẫu import loại văn bản giao việc
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\TaskAssignment\Imports\LookupImport::TEMPLATE_LABELS),
            'import-types-template.xlsx'
        );
    }
}
