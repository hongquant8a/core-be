<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\SaveBeneficiaryDocumentRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryDocumentCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryDocumentResource;
use App\Modules\Beneficiary\Services\BeneficiaryDocumentService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Tài liệu hồ sơ
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Sub-resource dạng A (1–n có tệp): mỗi tài liệu có tên và nhiều tệp đính kèm.
 */
class BeneficiaryDocumentController extends Controller
{
    public function __construct(private readonly BeneficiaryDocumentService $service) {}

    /**
     * Danh sách tài liệu của một hồ sơ
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     *
     * @queryParam search string Tìm theo tên tài liệu.
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, created_at. Example: id
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryDocumentCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDocument paginate=10
     */
    public function index(FilterRequest $request, Beneficiary $beneficiary)
    {
        $items = $this->service->index($beneficiary, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryDocumentCollection($items));
    }

    /**
     * Chi tiết tài liệu
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam document integer required ID tài liệu. Example: 9
     */
    public function show(Beneficiary $beneficiary, BeneficiaryDocument $document)
    {
        return $this->successResource(new BeneficiaryDocumentResource($this->service->show($document)));
    }

    /**
     * Thêm tài liệu
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function store(SaveBeneficiaryDocumentRequest $request, Beneficiary $beneficiary)
    {
        $data = $request->validated();
        $data['files'] = $request->file('files', []);

        return $this->successResource(
            new BeneficiaryDocumentResource($this->service->store($beneficiary, $data)),
            'Thêm tài liệu thành công'
        );
    }

    /**
     * Cập nhật tài liệu
     *
     * Gọi bằng POST kèm `_method=PUT` — PHP không parse multipart trên PUT.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam document integer required ID tài liệu. Example: 9
     */
    public function update(
        SaveBeneficiaryDocumentRequest $request,
        Beneficiary $beneficiary,
        BeneficiaryDocument $document
    ) {
        $data = $request->validated();
        $data['files'] = $request->file('files', []);

        return $this->successResource(
            new BeneficiaryDocumentResource($this->service->update($document, $data)),
            'Cập nhật tài liệu thành công'
        );
    }

    /**
     * Xóa tài liệu
     *
     * Xóa mềm; tệp đính kèm giữ nguyên trên storage và phục hồi được khi restore.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam document integer required ID tài liệu. Example: 9
     */
    public function destroy(Beneficiary $beneficiary, BeneficiaryDocument $document)
    {
        $this->service->destroy($document);

        return $this->success(null, 'Xóa tài liệu thành công');
    }

    /**
     * Xóa tài liệu hàng loạt
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $deleted = $this->service->bulkDestroy($beneficiary, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa tài liệu thành công');
    }
}
