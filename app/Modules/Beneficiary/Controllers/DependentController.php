<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Requests\BulkDestroyDependentRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreDependentRelationRequest;
use App\Modules\Beneficiary\Requests\StoreDependentRequest;
use App\Modules\Beneficiary\Requests\UpdateDependentRequest;
use App\Modules\Beneficiary\Resources\DependentCollection;
use App\Modules\Beneficiary\Resources\DependentRelationResource;
use App\Modules\Beneficiary\Resources\DependentResource;
use App\Modules\Beneficiary\Resources\StatusHistoryCollection;
use App\Modules\Beneficiary\Services\DependentService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Thân nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý thân nhân và quan hệ với người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa,
 * xóa hàng loạt, xuất/nhập, quản lý quan hệ (relations), lịch sử thay đổi trạng thái.
 *
 * Dependent không có route bulk-status/{id}/status — không có cột status vòng đời như Beneficiary
 * (chỉ có eligibility_status sửa qua update() và trạng thái quan hệ pivot qua relations).
 */
class DependentController extends Controller
{
    public function __construct(private DependentService $dependentService) {}

    /**
     * Thống kê thân nhân
     *
     * @response 200 {"success": true, "data": {"total": 30, "alive": 28, "deceased": 2}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->dependentService->stats($request->all()));
    }

    /**
     * Danh sách thân nhân
     *
     * @queryParam search string Tìm theo họ tên hoặc CCCD.
     * @queryParam household_id integer Lọc theo hộ gia đình.
     * @queryParam sort_by string Sắp xếp theo: id, full_name, date_of_birth, created_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\DependentCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\Dependent paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->dependentService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new DependentCollection($items));
    }

    /**
     * Chi tiết thân nhân
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\DependentResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Dependent
     * @apiResourceAdditional success=true
     */
    public function show(Dependent $dependent)
    {
        return $this->successResource(new DependentResource($this->dependentService->show($dependent)));
    }

    /**
     * Tạo thân nhân
     *
     * @bodyParam full_name string required Họ tên. Example: Lê Thị C
     * @bodyParam gender string required Giới tính. Example: female
     *
     * @apiResource App\Modules\Beneficiary\Resources\DependentResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\Dependent
     * @apiResourceAdditional success=true message="Tạo thân nhân thành công!"
     */
    public function store(StoreDependentRequest $request)
    {
        $item = $this->dependentService->store($request->validated());

        return $this->successResource(new DependentResource($item), 'Tạo thân nhân thành công!', 201);
    }

    /**
     * Cập nhật thân nhân
     *
     * Nếu is_alive chuyển sang false, toàn bộ quan hệ pivot đang active của thân nhân này
     * tự động chuyển expired (trừ trường hợp truy lĩnh — chưa hỗ trợ ở bản đầu).
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\DependentResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Dependent
     * @apiResourceAdditional success=true message="Cập nhật thân nhân thành công!"
     */
    public function update(UpdateDependentRequest $request, Dependent $dependent)
    {
        $item = $this->dependentService->update($dependent, $request->validated());

        return $this->successResource(new DependentResource($item), 'Cập nhật thân nhân thành công!');
    }

    /**
     * Xóa thân nhân
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa thân nhân thành công!"}
     */
    public function destroy(Dependent $dependent)
    {
        $this->dependentService->destroy($dependent);

        return $this->success(null, 'Xóa thân nhân thành công!');
    }

    /**
     * Xóa hàng loạt thân nhân
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyDependentRequest $request)
    {
        $this->dependentService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Thêm quan hệ với người có công
     *
     * Tự động validate: tuổi >= 18 phải có eligibility_status phù hợp (studying/disabled_no_work_capacity)
     * mới cho status = active, ngược lại tự chuyển expired. is_alive = false luôn tạo expired.
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     * @bodyParam beneficiary_id integer required ID người có công liên quan. Example: 1
     * @bodyParam relationship_type string required Quan hệ. Example: child
     * @bodyParam eligible_from date required Ngày bắt đầu đủ điều kiện hưởng. Example: 2020-01-01
     *
     * @apiResource App\Modules\Beneficiary\Resources\DependentRelationResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDependentRelation
     * @apiResourceAdditional success=true message="Thêm quan hệ thành công!"
     */
    public function storeRelation(StoreDependentRelationRequest $request, Dependent $dependent)
    {
        $relation = $this->dependentService->addRelation($dependent, $request->validated());

        return $this->successResource(new DependentRelationResource($relation), 'Thêm quan hệ thành công!', 201);
    }

    /**
     * Xóa quan hệ với người có công
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     * @urlParam relation integer required ID quan hệ (beneficiary_dependent_relations.id). Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa quan hệ thành công!"}
     */
    public function destroyRelation(Dependent $dependent, int $relation)
    {
        $this->dependentService->removeRelation($dependent, $relation);

        return $this->success(null, 'Xóa quan hệ thành công!');
    }

    /**
     * Lịch sử thay đổi trạng thái
     *
     * @urlParam dependent integer required ID thân nhân. Example: 1
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\StatusHistoryCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\StatusHistory paginate=10
     * @apiResourceAdditional success=true
     */
    public function statusHistories(FilterRequest $request, Dependent $dependent)
    {
        $items = $this->dependentService->statusHistories($dependent, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new StatusHistoryCollection($items));
    }

    /**
     * Xuất Excel thân nhân
     *
     * Xuất ra các trường: id, full_name, date_of_birth, gender, id_number, household_code, is_alive,
     * created_by, updated_by, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return $this->dependentService->export($request->all());
    }

    /**
     * Import thân nhân
     *
     * Cột bắt buộc: full_name, gender. Cột không bắt buộc: date_of_birth, id_number.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import thân nhân thành công."}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $this->dependentService->import($request->file('file'));

        return $this->success(null, 'Import thân nhân thành công.');
    }

    /**
     * Tải mẫu import thân nhân
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Beneficiary\Imports\DependentImport::TEMPLATE_LABELS, \App\Modules\Beneficiary\Imports\DependentImport::TEMPLATE_EXAMPLES),
            'import-dependents-template.xlsx'
        );
    }
}
