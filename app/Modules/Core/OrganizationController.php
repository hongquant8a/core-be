<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Requests\BulkDestroyOrganizationRequest;
use App\Modules\Core\Requests\BulkUpdateStatusOrganizationRequest;
use App\Modules\Core\Requests\ChangeStatusOrganizationRequest;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Requests\ImportOrganizationRequest;
use App\Modules\Core\Requests\StoreOrganizationRequest;
use App\Modules\Core\Requests\UpdateOrganizationRequest;
use App\Modules\Core\Resources\OrganizationCollection;
use App\Modules\Core\Resources\OrganizationResource;
use App\Modules\Core\Resources\OrganizationTreeResource;
use App\Modules\Core\Resources\PublicOptionResource;
use App\Modules\Core\Services\OrganizationService;
use Illuminate\Http\Request;

/**
 * @group Core - Organization
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý tổ chức (organization): stats, index, show, store, update, destroy, bulk delete, bulk status, change status, export, import.
 */
class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $organizationService) {}

    /**
     * Danh sách organization công khai
     *
     * Trả về danh sách organization đang hoạt động (active), thứ tự theo cây, dùng cho các chức năng công khai.
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm (name, slug). Example: cong-ty
     *
     * @apiResourceCollection App\Modules\Core\Resources\OrganizationCollection
     *
     * @apiResourceModel App\Modules\Core\Models\Organization
     *
     * @apiResourceAdditional success=true
     */
    public function public(FilterRequest $request)
    {
        $items = $this->organizationService->publicList($request->all());

        return $this->successCollection(new OrganizationCollection($items));
    }

    /**
     * Danh sách organization công khai cho dropdown
     *
     * Trả về dữ liệu tối giản chỉ gồm id, name, description để tối ưu payload cho dropdown.
     *
     * @unauthenticated
     *
     * @queryParam search string Từ khóa tìm kiếm (name, slug). Example: cong-ty
     *
     * @apiResourceCollection App\Modules\Core\Resources\PublicOptionResource
     *
     * @apiResourceModel App\Modules\Core\Models\Organization
     *
     * @apiResourceAdditional success=true
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->organizationService->publicOptions($request->all());

        return $this->successCollection(PublicOptionResource::collection($items));
    }

    /**
     * Thống kê organization
     *
     * Tổng số, đang kích hoạt (active), không kích hoạt (inactive). Áp dụng cùng bộ lọc với index.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, slug). Example: cong-ty
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d). Example: 2026-02-01
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d). Example: 2026-02-17
     * @queryParam sort_by string Sắp xếp theo: id, name, slug, status, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 10, "active": 5, "inactive": 5}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->organizationService->stats($request->all()));
    }

    /**
     * Danh sách organization
     *
     * Lấy danh sách có phân trang, lọc và sắp xếp.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, slug). Example: cong-ty
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d). Example: 2026-02-01
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d). Example: 2026-02-17
     * @queryParam sort_by string Sắp xếp theo: id, name, slug, status, created_at, updated_at. Example: id
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\Core\Resources\OrganizationCollection
     *
     * @apiResourceModel App\Modules\Core\Models\Organization paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->organizationService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new OrganizationCollection($items));
    }

    /**
     * Cây organization (toàn bộ cây, không phân trang). Cấu trúc parent_id.
     *
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     *
     * @response 200 {"success": true, "data": [{"id": 1, "name": "Công ty A", "slug": "cong-ty-a", "status": "active", "parent_id": null, "children": []}]}
     */
    public function tree(Request $request)
    {
        $tree = $this->organizationService->tree($request->status);

        return $this->successCollection(OrganizationTreeResource::collection($tree));
    }

    /**
     * Chi tiết organization
     *
     * @urlParam organization integer required ID organization. Example: 1
     *
     * @apiResource App\Modules\Core\Resources\OrganizationResource
     *
     * @apiResourceModel App\Modules\Core\Models\Organization with=parent,children
     *
     * @apiResourceAdditional success=true
     */
    public function show(Organization $organization)
    {
        $organization = $this->organizationService->show($organization);

        return $this->successResource(new OrganizationResource($organization));
    }

    /**
     * Tạo organization mới
     *
     * @bodyParam name string required Tên organization. Example: Công ty A
     * @bodyParam slug string Slug (nếu không gửi sẽ tự sinh từ name). Example: cong-ty-a
     * @bodyParam description string Mô tả. Example: Tổ chức quản trị
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     * @bodyParam parent_id integer ID organization cha (null = gốc). Example: null
     * @bodyParam sort_order integer Thứ tự. Example: 0
     *
     * @apiResource App\Modules\Core\Resources\OrganizationResource status=201
     *
     * @apiResourceModel App\Modules\Core\Models\Organization
     *
     * @apiResourceAdditional success=true message="Organization đã được tạo thành công!"
     */
    public function store(StoreOrganizationRequest $request)
    {
        $organization = $this->organizationService->store($request->validated());

        return $this->successResource(new OrganizationResource($organization), 'Organization đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật organization
     *
     * @urlParam organization integer required ID organization. Example: 1
     *
     * @bodyParam name string Tên organization. Example: Công ty A
     * @bodyParam slug string Slug. Example: cong-ty-a
     * @bodyParam description string Mô tả. Example: Tổ chức quản trị
     * @bodyParam status string Trạng thái: active, inactive. Example: inactive
     * @bodyParam parent_id integer ID organization cha (null = gốc). Example: null
     * @bodyParam sort_order integer Thứ tự. Example: 0
     *
     * @apiResource App\Modules\Core\Resources\OrganizationResource
     *
     * @apiResourceModel App\Modules\Core\Models\Organization with=parent,children
     *
     * @apiResourceAdditional success=true message="Organization đã được cập nhật!"
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $result = $this->organizationService->update($organization, $request->validated());
        if (! $result['ok']) {
            return $this->error($result['message'], $result['code'], null, $result['error_code']);
        }

        return $this->successResource(new OrganizationResource($result['organization']), 'Organization đã được cập nhật!');
    }

    /**
     * Xóa organization
     *
     * @urlParam organization integer required ID organization. Example: 1
     *
     * @response 200 {"success": true, "message": "Organization đã được xóa!"}
     */
    public function destroy(Organization $organization)
    {
        $this->organizationService->destroy($organization);

        return $this->success(null, 'Organization đã được xóa!');
    }

    /**
     * Xóa hàng loạt organization
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công các organization được chọn!"}
     */
    public function bulkDestroy(BulkDestroyOrganizationRequest $request)
    {
        $this->organizationService->bulkDestroy($request->ids);

        return $this->success(null, 'Đã xóa thành công các organization được chọn!');
    }

    /**
     * Cập nhật trạng thái organization hàng loạt
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     * @bodyParam status string required Trạng thái: active, inactive. Example: active
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái organization thành công."}
     */
    public function bulkUpdateStatus(BulkUpdateStatusOrganizationRequest $request)
    {
        $this->organizationService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái organization thành công.');
    }

    /**
     * Thay đổi trạng thái organization
     *
     * @urlParam organization integer required ID organization. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive. Example: inactive
     *
     * @apiResource App\Modules\Core\Resources\OrganizationResource
     *
     * @apiResourceModel App\Modules\Core\Models\Organization with=parent,children
     *
     * @apiResourceAdditional success=true message="Cập nhật trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusOrganizationRequest $request, Organization $organization)
    {
        $organization = $this->organizationService->changeStatus($organization, $request->status);

        return $this->successResource(new OrganizationResource($organization), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Xuất danh sách organization
     *
     * Áp dụng cùng bộ lọc với index. Xuất ra các trường: id, name, slug, description, status, parent_id, parent_slug, sort_order, depth, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, slug).
     * @queryParam status string Lọc theo trạng thái: active, inactive.
     * @queryParam from_date date Lọc từ ngày tạo (created_at) (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (created_at) (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, name, slug, status, created_at, updated_at.
     * @queryParam sort_order string Thứ tự: asc, desc.
     */
    public function export(FilterRequest $request)
    {
        return $this->organizationService->export($request->all());
    }

    /**
     * Nhập danh sách organization
     *
     * Cột bắt buộc: name. Cột không bắt buộc: slug, description, status (mặc định "active"), parent_id.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import organization thành công."}
     */
    public function import(ImportOrganizationRequest $request)
    {
        $this->organizationService->import($request->file('file'));

        return $this->success(null, 'Import organization thành công.');
    }

    /**
     * Tải mẫu import tổ chức
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Core\Imports\OrganizationsImport::TEMPLATE_LABELS, \App\Modules\Core\Imports\OrganizationsImport::TEMPLATE_EXAMPLES),
            'import-organizations-template.xlsx'
        );
    }
}
