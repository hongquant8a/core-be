<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\SubsidyGrant;
use App\Modules\Beneficiary\Requests\ChangeStatusSubsidyGrantRequest;
use App\Modules\Beneficiary\Requests\StoreSubsidyGrantRequest;
use App\Modules\Beneficiary\Resources\SubsidyGrantCollection;
use App\Modules\Beneficiary\Resources\SubsidyGrantResource;
use App\Modules\Beneficiary\Services\SubsidyGrantService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Trợ cấp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Cấp & dừng trợ cấp cho người có công/thân nhân. Chỉ có index/store/changeStatus — grant chỉ phát
 * sinh qua hành động nghiệp vụ (cấp trợ cấp), không phải danh mục CRUD tự do
 * (không có update/destroy/bulkDestroy/import/export — xem lý do ở kế hoạch triển khai).
 */
class SubsidyGrantController extends Controller
{
    public function __construct(private SubsidyGrantService $subsidyGrantService) {}

    /**
     * Danh sách trợ cấp
     *
     * @queryParam subject_type string Lọc theo loại đối tượng: beneficiary, dependent (giá trị FQCN/alias morph).
     * @queryParam subject_id integer Lọc theo ID đối tượng.
     * @queryParam status string Lọc theo trạng thái: active, terminated, suspended.
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\SubsidyGrantCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyGrant paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->subsidyGrantService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new SubsidyGrantCollection($items));
    }

    /**
     * Cấp trợ cấp
     *
     * Tự động điền amount từ chính sách nếu không truyền, chặn nếu chính sách đã hết hiệu lực.
     *
     * @bodyParam subject_type string required beneficiary hoặc dependent. Example: beneficiary
     * @bodyParam subject_id integer required ID đối tượng nhận. Example: 1
     * @bodyParam beneficiary_subsidy_policy_id integer required ID chính sách áp dụng. Example: 1
     * @bodyParam granted_from date required Ngày bắt đầu cấp. Example: 2024-01-01
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyGrantResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyGrant
     * @apiResourceAdditional success=true message="Cấp trợ cấp thành công!"
     */
    public function store(StoreSubsidyGrantRequest $request)
    {
        $item = $this->subsidyGrantService->store($request->validated());

        return $this->successResource(new SubsidyGrantResource($item), 'Cấp trợ cấp thành công!', 201);
    }

    /**
     * Đổi trạng thái trợ cấp (dừng/tạm dừng)
     *
     * @urlParam subsidyGrant integer required ID trợ cấp. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: terminated
     * @bodyParam termination_reason string Lý do dừng (bắt buộc nếu status = terminated).
     *
     * @apiResource App\Modules\Beneficiary\Resources\SubsidyGrantResource
     * @apiResourceModel App\Modules\Beneficiary\Models\SubsidyGrant
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusSubsidyGrantRequest $request, SubsidyGrant $subsidyGrant)
    {
        $item = $this->subsidyGrantService->changeStatus(
            $subsidyGrant,
            $request->status,
            $request->input('termination_reason'),
        );

        return $this->successResource(new SubsidyGrantResource($item), 'Đổi trạng thái thành công!');
    }
}
