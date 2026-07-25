<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryDocumentRequest;
use App\Modules\Beneficiary\Requests\StoreBeneficiaryDocumentRequest;
use App\Modules\Beneficiary\Requests\UpdateBeneficiaryDocumentRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryDocumentCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryDocumentResource;
use App\Modules\Beneficiary\Services\BeneficiaryDocumentService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Giấy tờ hồ sơ
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý danh sách giấy tờ/hồ sơ đính kèm của người có công: mỗi bản ghi = 1 tên giấy tờ + nhiều tập tin.
 * Không có export/import (đính kèm tập tin không phù hợp file phẳng).
 */
class BeneficiaryDocumentController extends Controller
{
    public function __construct(private BeneficiaryDocumentService $service) {}

    /**
     * Danh sách giấy tờ
     *
     * @queryParam search string Tìm theo tên giấy tờ.
     * @queryParam beneficiary_id integer Lọc theo người có công.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryDocumentCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDocument paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryDocumentCollection($items));
    }

    /**
     * Chi tiết giấy tờ
     *
     * @urlParam document integer required ID giấy tờ. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryDocumentResource
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDocument
     * @apiResourceAdditional success=true
     */
    public function show(BeneficiaryDocument $document)
    {
        return $this->successResource(new BeneficiaryDocumentResource($this->service->show($document)));
    }

    /**
     * Tạo giấy tờ
     *
     * @bodyParam beneficiary_id integer required ID người có công. Example: 1
     * @bodyParam name string required Tên giấy tờ. Example: Giấy chứng nhận thương binh
     * @bodyParam files file[] Danh sách tập tin đính kèm (nhiều file, mỗi file ≤ 10MB).
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryDocumentResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDocument
     * @apiResourceAdditional success=true message="Tạo giấy tờ thành công!"
     */
    public function store(StoreBeneficiaryDocumentRequest $request)
    {
        $item = $this->service->store($request->validated());

        return $this->successResource(new BeneficiaryDocumentResource($item), 'Tạo giấy tờ thành công!', 201);
    }

    /**
     * Cập nhật giấy tờ
     *
     * @urlParam document integer required ID giấy tờ. Example: 1
     * @bodyParam name string Tên giấy tờ. Example: Giấy chứng nhận thương binh
     * @bodyParam files file[] Tập tin đính kèm thêm.
     * @bodyParam files_deleted integer[] Danh sách ID tập tin (media) cần xóa. Example: [5]
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryDocumentResource
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDocument
     * @apiResourceAdditional success=true message="Cập nhật giấy tờ thành công!"
     */
    public function update(UpdateBeneficiaryDocumentRequest $request, BeneficiaryDocument $document)
    {
        $item = $this->service->update($document, $request->validated());

        return $this->successResource(new BeneficiaryDocumentResource($item), 'Cập nhật giấy tờ thành công!');
    }

    /**
     * Xóa giấy tờ
     *
     * @urlParam document integer required ID giấy tờ. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa giấy tờ thành công!"}
     */
    public function destroy(BeneficiaryDocument $document)
    {
        $this->service->destroy($document);

        return $this->success(null, 'Xóa giấy tờ thành công!');
    }

    /**
     * Xóa hàng loạt giấy tờ
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyBeneficiaryDocumentRequest $request)
    {
        $this->service->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }
}
