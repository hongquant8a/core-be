<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Requests\BulkDestroyPermissionRequest;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Requests\ImportPermissionRequest;
use App\Modules\Core\Requests\StorePermissionRequest;
use App\Modules\Core\Requests\UpdatePermissionRequest;
use App\Modules\Core\Resources\PermissionCollection;
use App\Modules\Core\Resources\PermissionResource;
use App\Modules\Core\Resources\PermissionTreeResource;
use App\Modules\Core\Services\PermissionService;
use Illuminate\Http\Request;

/**
 * @group Core - Permission
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý quyền (permission): stats, index, show, store, update, destroy, bulk delete, export, import.
 */
class PermissionController extends Controller
{
    public function __construct(private PermissionService $permissionService) {}

    /**
     * Thống kê permission
     *
     * Tổng số bản ghi sau khi áp dụng bộ lọc.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, guard_name, description). Example: posts
     * @queryParam parent_id integer Lọc theo permission cha. Example: 152
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d). Example: 2026-02-01
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d). Example: 2026-02-17
     * @queryParam sort_by string Sắp xếp theo: id, name, guard_name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 20}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->permissionService->stats($request->all()));
    }

    /**
     * Danh sách permission
     *
     * Lấy danh sách có phân trang, lọc và sắp xếp.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, guard_name, description). Example: posts
     * @queryParam parent_id integer Lọc theo permission cha. Example: 152
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d). Example: 2026-02-01
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d). Example: 2026-02-17
     * @queryParam sort_by string Sắp xếp theo: id, name, guard_name, description, sort_order, parent_id, created_at, updated_at. Example: sort_order
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\Core\Resources\PermissionCollection
     *
     * @apiResourceModel App\Modules\Core\Models\Permission paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->permissionService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new PermissionCollection($items));
    }

    /**
     * Cây permission (toàn bộ cây, không phân trang). Để hiển thị nhóm quyền trên frontend.
     *
     * @queryParam parent_id integer Lọc theo parent_id (null = gốc). Example: null
     *
     * @response 200 {"success": true, "data": [{"id": 1, "name": "posts", "guard_name": "web", "description": "Quản lý bài viết", "sort_order": 0, "parent_id": null, "children": []}]}
     */
    public function tree(Request $request)
    {
        $tree = $this->permissionService->tree($request->has('parent_id'), $request->parent_id);

        return $this->successCollection(PermissionTreeResource::collection($tree));
    }

    /**
     * Chi tiết permission
     *
     * @urlParam permission integer required ID permission. Example: 1
     *
     * @apiResource App\Modules\Core\Resources\PermissionResource
     *
     * @apiResourceModel App\Modules\Core\Models\Permission with=parent,children
     *
     * @apiResourceAdditional success=true
     */
    public function show(Permission $permission)
    {
        $permission = $this->permissionService->show($permission);

        return $this->successResource(new PermissionResource($permission));
    }

    /**
     * Tạo permission mới
     *
     * @bodyParam name string required Tên permission. Example: posts.create
     * @bodyParam guard_name string Guard name (mặc định web). Example: web
     * @bodyParam description string Mô tả hiển thị trên frontend.
     * @bodyParam sort_order integer Thứ tự sắp xếp. Example: 0
     * @bodyParam parent_id integer ID permission cha (null = gốc/nhóm).
     *
     * @apiResource App\Modules\Core\Resources\PermissionResource status=201
     *
     * @apiResourceModel App\Modules\Core\Models\Permission
     *
     * @apiResourceAdditional success=true message="Quyền đã được tạo thành công!"
     */
    public function store(StorePermissionRequest $request)
    {
        $permission = $this->permissionService->store($request->validated());

        return $this->successResource(new PermissionResource($permission), 'Quyền đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật permission
     *
     * @urlParam permission integer required ID permission. Example: 1
     *
     * @bodyParam name string Tên permission. Example: posts.update
     * @bodyParam guard_name string Guard name. Example: web
     * @bodyParam description string Mô tả.
     * @bodyParam sort_order integer Thứ tự sắp xếp.
     * @bodyParam parent_id integer ID permission cha (null = gốc).
     *
     * @apiResource App\Modules\Core\Resources\PermissionResource
     *
     * @apiResourceModel App\Modules\Core\Models\Permission
     *
     * @apiResourceAdditional success=true message="Quyền đã được cập nhật!"
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission = $this->permissionService->update($permission, $request->validated());

        return $this->successResource(new PermissionResource($permission), 'Quyền đã được cập nhật!');
    }

    /**
     * Xóa permission
     *
     * @urlParam permission integer required ID permission. Example: 1
     *
     * @response 200 {"success": true, "message": "Quyền đã được xóa!"}
     */
    public function destroy(Permission $permission)
    {
        $this->permissionService->destroy($permission);

        return $this->success(null, 'Quyền đã được xóa!');
    }

    /**
     * Xóa hàng loạt permission
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công các quyền được chọn!"}
     */
    public function bulkDestroy(BulkDestroyPermissionRequest $request)
    {
        $this->permissionService->bulkDestroy($request->ids);

        return $this->success(null, 'Đã xóa thành công các quyền được chọn!');
    }

    /**
     * Xuất danh sách permission
     *
     * Áp dụng cùng bộ lọc với index. Xuất ra các trường: id, name, guard_name, description, sort_order, parent_id, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, guard_name).
     * @queryParam parent_id integer Lọc theo permission cha. Example: 152
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, name, guard_name, created_at, updated_at.
     * @queryParam sort_order string Thứ tự: asc, desc.
     */
    public function export(FilterRequest $request)
    {
        return $this->permissionService->export($request->all());
    }

    /**
     * Nhập danh sách permission
     *
     * Cột bắt buộc: name. Cột không bắt buộc: guard_name (mặc định "web"), description, sort_order, parent_id.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import quyền thành công."}
     */
    public function import(ImportPermissionRequest $request)
    {
        $this->permissionService->import($request->file('file'));

        return $this->success(null, 'Import quyền thành công.');
    }

    /**
     * Tải mẫu import quyền
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Core\Imports\PermissionsImport::TEMPLATE_LABELS, \App\Modules\Core\Imports\PermissionsImport::TEMPLATE_EXAMPLES),
            'import-permissions-template.xlsx'
        );
    }
}
