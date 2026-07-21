<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Beneficiary\Requests\BulkDestroySubsidyPolicyRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreSubsidyPolicyRequest;
use App\Modules\Beneficiary\Requests\UpdateSubsidyPolicyRequest;
use App\Modules\Beneficiary\Resources\SubsidyPolicyCollection;
use App\Modules\Beneficiary\Resources\SubsidyPolicyResource;
use App\Modules\Beneficiary\Services\SubsidyPolicyService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Chính sách trợ cấp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục mức trợ cấp theo quy định pháp luật (organization_id NULL = áp dụng toàn TP).
 * Không có route bulk-status/{id}/status — không có cột status, hiệu lực xác định bởi effective_from/to.
 */
class SubsidyPolicyController extends Controller
{
    public function __construct(private SubsidyPolicyService $subsidyPolicyService) {}

    /**
     * Thống kê chính sách trợ cấp
     *
     * @response 200 {"success": true, "data": {"total": 6, "effective": 4}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->subsidyPolicyService->stats($request->all()));
    }

    /**
     * Danh sách chính sách trợ cấp
     *
     * @queryParam beneficiary_type string Lọc theo loại đối tượng.
     * @queryParam sort_by string Sắp xếp theo: id, amount, effective_from, created_at. Example: effective_from
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\SubsidyPolicyCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyPolicy paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->subsidyPolicyService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new SubsidyPolicyCollection($items));
    }

    /**
     * Chi tiết chính sách trợ cấp
     *
     * @urlParam subsidyPolicy integer required ID chính sách. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyPolicyResource
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyPolicy
     * @apiResourceAdditional success=true
     */
    public function show(SubsidyPolicy $subsidyPolicy)
    {
        return $this->successResource(new SubsidyPolicyResource($this->subsidyPolicyService->show($subsidyPolicy)));
    }

    /**
     * Tạo chính sách trợ cấp
     *
     * @bodyParam amount numeric required Mức trợ cấp. Example: 3500000
     * @bodyParam legal_basis string required Căn cứ pháp lý. Example: Nghị định 75/2021/NĐ-CP
     * @bodyParam effective_from date required Ngày hiệu lực. Example: 2021-07-01
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyPolicyResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyPolicy
     * @apiResourceAdditional success=true message="Tạo chính sách trợ cấp thành công!"
     */
    public function store(StoreSubsidyPolicyRequest $request)
    {
        $item = $this->subsidyPolicyService->store($request->validated());

        return $this->successResource(new SubsidyPolicyResource($item), 'Tạo chính sách trợ cấp thành công!', 201);
    }

    /**
     * Cập nhật chính sách trợ cấp
     *
     * @urlParam subsidyPolicy integer required ID chính sách. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyPolicyResource
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyPolicy
     * @apiResourceAdditional success=true message="Cập nhật chính sách trợ cấp thành công!"
     */
    public function update(UpdateSubsidyPolicyRequest $request, SubsidyPolicy $subsidyPolicy)
    {
        $item = $this->subsidyPolicyService->update($subsidyPolicy, $request->validated());

        return $this->successResource(new SubsidyPolicyResource($item), 'Cập nhật chính sách trợ cấp thành công!');
    }

    /**
     * Xóa chính sách trợ cấp
     *
     * @urlParam subsidyPolicy integer required ID chính sách. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa chính sách trợ cấp thành công!"}
     */
    public function destroy(SubsidyPolicy $subsidyPolicy)
    {
        $this->subsidyPolicyService->destroy($subsidyPolicy);

        return $this->success(null, 'Xóa chính sách trợ cấp thành công!');
    }

    /**
     * Xóa hàng loạt chính sách trợ cấp
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroySubsidyPolicyRequest $request)
    {
        $this->subsidyPolicyService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Ban hành chính sách mới thay thế (renew)
     *
     * Đóng chính sách cũ (effective_to = ngày trước effective_from mới), tạo chính sách mới,
     * và tự động nối tiếp toàn bộ subsidy_grants đang active thuộc chính sách cũ sang chính sách mới
     * (đóng grant cũ + tạo grant mới, giữ nguyên lịch sử — không sửa trực tiếp amount trên grant cũ).
     *
     * @urlParam subsidyPolicy integer required ID chính sách cũ. Example: 1
     * @bodyParam amount numeric required Mức trợ cấp mới. Example: 4000000
     * @bodyParam legal_basis string required Căn cứ pháp lý mới. Example: Nghị định 55/2023/NĐ-CP
     * @bodyParam effective_from date required Ngày hiệu lực chính sách mới. Example: 2023-07-01
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyPolicyResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyPolicy
     * @apiResourceAdditional success=true message="Ban hành chính sách mới thành công!"
     */
    public function renew(StoreSubsidyPolicyRequest $request, SubsidyPolicy $subsidyPolicy)
    {
        $item = $this->subsidyPolicyService->renew($subsidyPolicy, $request->validated());

        return $this->successResource(new SubsidyPolicyResource($item), 'Ban hành chính sách mới thành công!', 201);
    }

    /**
     * Xuất Excel chính sách trợ cấp
     *
     * Xuất ra các trường: id, beneficiary_type, amount, unit, legal_basis, effective_from, effective_to,
     * created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return $this->subsidyPolicyService->export($request->all());
    }

    /**
     * Import chính sách trợ cấp
     *
     * Cột bắt buộc: amount, legal_basis, effective_from. Cột không bắt buộc: beneficiary_type, unit.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import chính sách trợ cấp thành công."}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $this->subsidyPolicyService->import($request->file('file'));

        return $this->success(null, 'Import chính sách trợ cấp thành công.');
    }

    /**
     * Tải mẫu import chính sách trợ cấp
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Beneficiary\Imports\SubsidyPolicyImport::TEMPLATE_LABELS, \App\Modules\Beneficiary\Imports\SubsidyPolicyImport::TEMPLATE_EXAMPLES),
            'import-subsidy-policies-template.xlsx'
        );
    }
}
