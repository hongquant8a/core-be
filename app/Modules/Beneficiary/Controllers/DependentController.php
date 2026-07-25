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
use App\Modules\Beneficiary\Services\DependentService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Thân nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý thân nhân và quan hệ với người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa,
 * xóa hàng loạt, xuất/nhập, quản lý quan hệ (relations).
 *
 * Dependent không có route bulk-status/{id}/status — chỉ lưu thông tin cơ bản, không có cột trạng thái vòng đời.
 */
class DependentController extends Controller
{
    public function __construct(private DependentService $dependentService) {}

    /**
     * Thống kê thân nhân
     *
     * @response 200 {"success": true, "data": {"total": 30, "linked": 25}}
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
     * @urlParam dependent integer required ID thân nhân. Example: 1
     * @bodyParam beneficiary_id integer required ID người có công liên quan. Example: 1
     * @bodyParam relationship_type string required Quan hệ. Example: child
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
     * Xuất Excel thân nhân
     *
     * Xuất ra các trường: id, full_name, date_of_birth, gender, id_number, head_id_number, residential_area,
     * phone, latitude, longitude, note, created_by, updated_by, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return $this->dependentService->export($request->all());
    }

    /**
     * Import thân nhân
     *
     * Cột bắt buộc: full_name, gender. Cột không bắt buộc: date_of_birth, id_number, phone, residential_area (tra theo tên tổ dân phố), latitude, longitude, note.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * Dòng lỗi validation được bỏ qua (các dòng hợp lệ vẫn import), trả về `failed_count` và
     * `errors` (số dòng, cột, thông báo, giá trị) để cán bộ sửa và nhập lại.
     *
     * @response 200 {"success": true, "message": "Import thân nhân hoàn tất — đã bỏ qua 1 dòng lỗi, vui lòng kiểm tra và nhập lại các dòng này.", "data": {"failed_count": 1, "errors": [{"row": 4, "column": "Giới tính", "errors": ["Giới tính không được để trống."], "values": {"Họ tên": "Lê Thị C"}}]}}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $failures = $this->dependentService->import($request->file('file'));

        return $this->importResult($failures, 'thân nhân');
    }

    /**
     * Tải mẫu import thân nhân
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Beneficiary\Imports\DependentImport::TEMPLATE_LABELS, \App\Modules\Beneficiary\Imports\DependentImport::TEMPLATE_EXAMPLES, \App\Modules\Beneficiary\Imports\DependentImport::REQUIRED_KEYS, \App\Modules\Beneficiary\Imports\DependentImport::templateNotes(), \App\Modules\Beneficiary\Imports\DependentImport::templateOptions()),
            'import-dependents-template.xlsx'
        );
    }
}
