<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Beneficiary\Requests\BulkDestroyResidentialAreaRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreResidentialAreaRequest;
use App\Modules\Beneficiary\Requests\UpdateResidentialAreaRequest;
use App\Modules\Beneficiary\Resources\ResidentialAreaCollection;
use App\Modules\Beneficiary\Resources\ResidentialAreaResource;
use App\Modules\Beneficiary\Services\ResidentialAreaService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Tổ dân phố
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục tổ dân phố/khu vực, dùng để gán cho hộ gia đình. Không có cột status (không có
 * vòng đời trạng thái) — không có route bulk-status/{id}/status, giống Household.
 */
class ResidentialAreaController extends Controller
{
    public function __construct(private ResidentialAreaService $residentialAreaService) {}

    /**
     * Thống kê tổ dân phố
     *
     * @queryParam search string Tìm theo tên tổ dân phố.
     *
     * @response 200 {"success": true, "data": {"total": 12, "total_households": 340}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->residentialAreaService->stats($request->all()));
    }

    /**
     * Danh sách tổ dân phố
     *
     * @queryParam search string Tìm theo tên tổ dân phố.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\ResidentialAreaCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\ResidentialArea paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->residentialAreaService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new ResidentialAreaCollection($items));
    }

    /**
     * Chi tiết tổ dân phố
     *
     * @urlParam residentialArea integer required ID tổ dân phố. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\ResidentialAreaResource
     * @apiResourceModel App\Modules\Beneficiary\Models\ResidentialArea
     * @apiResourceAdditional success=true
     */
    public function show(ResidentialArea $residentialArea)
    {
        return $this->successResource(new ResidentialAreaResource($this->residentialAreaService->show($residentialArea)));
    }

    /**
     * Tạo tổ dân phố
     *
     * @bodyParam name string required Tên tổ dân phố. Example: Tổ 5
     *
     * @apiResource App\Modules\Beneficiary\Resources\ResidentialAreaResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\ResidentialArea
     * @apiResourceAdditional success=true message="Tạo tổ dân phố thành công!"
     */
    public function store(StoreResidentialAreaRequest $request)
    {
        $item = $this->residentialAreaService->store($request->validated());

        return $this->successResource(new ResidentialAreaResource($item), 'Tạo tổ dân phố thành công!', 201);
    }

    /**
     * Cập nhật tổ dân phố
     *
     * @urlParam residentialArea integer required ID tổ dân phố. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\ResidentialAreaResource
     * @apiResourceModel App\Modules\Beneficiary\Models\ResidentialArea
     * @apiResourceAdditional success=true message="Cập nhật tổ dân phố thành công!"
     */
    public function update(UpdateResidentialAreaRequest $request, ResidentialArea $residentialArea)
    {
        $item = $this->residentialAreaService->update($residentialArea, $request->validated());

        return $this->successResource(new ResidentialAreaResource($item), 'Cập nhật tổ dân phố thành công!');
    }

    /**
     * Xóa tổ dân phố
     *
     * @urlParam residentialArea integer required ID tổ dân phố. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa tổ dân phố thành công!"}
     */
    public function destroy(ResidentialArea $residentialArea)
    {
        $this->residentialAreaService->destroy($residentialArea);

        return $this->success(null, 'Xóa tổ dân phố thành công!');
    }

    /**
     * Xóa hàng loạt tổ dân phố
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyResidentialAreaRequest $request)
    {
        $this->residentialAreaService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Xuất Excel tổ dân phố
     *
     * Xuất ra các trường: id, name, code, household_count, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Tìm theo tên tổ dân phố.
     */
    public function export(FilterRequest $request)
    {
        return $this->residentialAreaService->export($request->all());
    }

    /**
     * Import tổ dân phố
     *
     * Cột bắt buộc: name. Cột không bắt buộc: code.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import tổ dân phố thành công."}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $this->residentialAreaService->import($request->file('file'));

        return $this->success(null, 'Import tổ dân phố thành công.');
    }

    /**
     * Tải mẫu import tổ dân phố
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Beneficiary\Imports\ResidentialAreaImport::TEMPLATE_LABELS, \App\Modules\Beneficiary\Imports\ResidentialAreaImport::TEMPLATE_EXAMPLES),
            'import-residential-areas-template.xlsx'
        );
    }
}
