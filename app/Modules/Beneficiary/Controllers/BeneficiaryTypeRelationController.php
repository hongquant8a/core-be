<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryTypeRelation;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\SaveBeneficiaryTypeRelationRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryTypeRelationCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryTypeRelationResource;
use App\Modules\Beneficiary\Services\BeneficiaryTypeRelationService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Đối tượng của người có công
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Sub-resource dạng D (n–n có thuộc tính): nối hồ sơ với danh mục Loại đối tượng, mang cờ
 * "đối tượng chính" và tệp đính kèm riêng cho từng dòng.
 *
 * Route lồng dưới `/beneficiaries/{beneficiary}` — `beneficiary_id` luôn lấy từ URL, KHÔNG
 * nhận từ body. Đó là cơ chế chặn IDOR.
 */
class BeneficiaryTypeRelationController extends Controller
{
    public function __construct(private readonly BeneficiaryTypeRelationService $service) {}

    /**
     * Danh sách đối tượng của một hồ sơ
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     *
     * @queryParam beneficiary_type_id integer Lọc theo loại đối tượng. Example: 2
     * @queryParam sort_by string Sắp xếp theo: id, created_at, updated_at. Example: id
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryTypeRelationCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryTypeRelation paginate=10
     */
    public function index(FilterRequest $request, Beneficiary $beneficiary)
    {
        $items = $this->service->index($beneficiary, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryTypeRelationCollection($items));
    }

    /**
     * Chi tiết một đối tượng
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam typeRelation integer required ID dòng đối tượng. Example: 5
     */
    public function show(Beneficiary $beneficiary, BeneficiaryTypeRelation $typeRelation)
    {
        return $this->successResource(new BeneficiaryTypeRelationResource($this->service->show($typeRelation)));
    }

    /**
     * Thêm đối tượng cho hồ sơ
     *
     * Gán lại một loại đối tượng đã từng bị xoá sẽ KHÔI PHỤC dòng cũ kèm tệp đính kèm,
     * không tạo dòng mới.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function store(SaveBeneficiaryTypeRelationRequest $request, Beneficiary $beneficiary)
    {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments', []);

        return $this->successResource(
            new BeneficiaryTypeRelationResource($this->service->store($beneficiary, $data)),
            'Thêm đối tượng thành công'
        );
    }

    /**
     * Cập nhật đối tượng
     *
     * Gọi bằng POST kèm `_method=PUT` — PHP không parse multipart trên PUT.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam typeRelation integer required ID dòng đối tượng. Example: 5
     */
    public function update(
        SaveBeneficiaryTypeRelationRequest $request,
        Beneficiary $beneficiary,
        BeneficiaryTypeRelation $typeRelation
    ) {
        $data = $request->validated();
        $data['attachments'] = $request->file('attachments', []);

        return $this->successResource(
            new BeneficiaryTypeRelationResource($this->service->update($typeRelation, $data)),
            'Cập nhật đối tượng thành công'
        );
    }

    /**
     * Xóa đối tượng
     *
     * Xóa mềm; tệp đính kèm giữ nguyên trên storage và phục hồi được khi restore.
     * Dòng đang là "đối tượng chính" bị xoá thì KHÔNG tự thăng dòng khác lên.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam typeRelation integer required ID dòng đối tượng. Example: 5
     */
    public function destroy(Beneficiary $beneficiary, BeneficiaryTypeRelation $typeRelation)
    {
        $this->service->destroy($typeRelation);

        return $this->success(null, 'Xóa đối tượng thành công');
    }

    /**
     * Xóa đối tượng hàng loạt
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $deleted = $this->service->bulkDestroy($beneficiary, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa đối tượng thành công');
    }
}
