<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDependent;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\SaveBeneficiaryDependentRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryDependentCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryDependentResource;
use App\Modules\Beneficiary\Services\BeneficiaryDependentService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Thân nhân
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Sub-resource dạng B (1–n không tệp): thân nhân là dòng con trực thuộc một hồ sơ, không
 * phải thực thể dùng chung. Hai hồ sơ cùng khai một người sẽ có hai dòng độc lập.
 */
class BeneficiaryDependentController extends Controller
{
    public function __construct(private readonly BeneficiaryDependentService $service) {}

    /**
     * Danh sách thân nhân của một hồ sơ
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     *
     * @queryParam search string Tìm theo họ tên, CCCD/CMND hoặc số điện thoại.
     * @queryParam relationship_id integer Lọc theo mối quan hệ. Example: 2
     * @queryParam residential_area_id integer Lọc theo tổ dân phố/thôn. Example: 3
     * @queryParam gender string Lọc theo giới tính: male, female, other. Example: female
     * @queryParam sort_by string Sắp xếp theo: id, full_name, birth_year, created_at. Example: id
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryDependentCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryDependent paginate=10
     */
    public function index(FilterRequest $request, Beneficiary $beneficiary)
    {
        $items = $this->service->index($beneficiary, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryDependentCollection($items));
    }

    /**
     * Chi tiết thân nhân
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam dependent integer required ID thân nhân. Example: 7
     */
    public function show(Beneficiary $beneficiary, BeneficiaryDependent $dependent)
    {
        return $this->successResource(new BeneficiaryDependentResource($this->service->show($dependent)));
    }

    /**
     * Thêm thân nhân
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function store(SaveBeneficiaryDependentRequest $request, Beneficiary $beneficiary)
    {
        return $this->successResource(
            new BeneficiaryDependentResource($this->service->store($beneficiary, $request->validated())),
            'Thêm thân nhân thành công'
        );
    }

    /**
     * Cập nhật thân nhân
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam dependent integer required ID thân nhân. Example: 7
     */
    public function update(
        SaveBeneficiaryDependentRequest $request,
        Beneficiary $beneficiary,
        BeneficiaryDependent $dependent
    ) {
        return $this->successResource(
            new BeneficiaryDependentResource($this->service->update($dependent, $request->validated())),
            'Cập nhật thân nhân thành công'
        );
    }

    /**
     * Xóa thân nhân
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     * @urlParam dependent integer required ID thân nhân. Example: 7
     */
    public function destroy(Beneficiary $beneficiary, BeneficiaryDependent $dependent)
    {
        $this->service->destroy($dependent);

        return $this->success(null, 'Xóa thân nhân thành công');
    }

    /**
     * Xóa thân nhân hàng loạt
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $deleted = $this->service->bulkDestroy($beneficiary, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa thân nhân thành công');
    }
}
