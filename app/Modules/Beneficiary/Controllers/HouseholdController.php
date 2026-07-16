<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Requests\BulkDestroyHouseholdRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreHouseholdRequest;
use App\Modules\Beneficiary\Requests\UpdateHouseholdRequest;
use App\Modules\Beneficiary\Resources\HouseholdCollection;
use App\Modules\Beneficiary\Resources\HouseholdResource;
use App\Modules\Beneficiary\Services\HouseholdService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Hộ gia đình
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý hộ gia đình có người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, xóa hàng loạt, xuất/nhập.
 */
class HouseholdController extends Controller
{
    public function __construct(private HouseholdService $householdService) {}

    /**
     * Thống kê hộ gia đình
     *
     * @queryParam search string Tìm theo tên chủ hộ hoặc mã hộ.
     * @queryParam residential_area_id integer Lọc theo tổ dân phố.
     *
     * @response 200 {"success": true, "data": {"total": 20, "total_members": 45}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->householdService->stats($request->all()));
    }

    /**
     * Danh sách hộ gia đình
     *
     * @queryParam search string Tìm theo tên chủ hộ hoặc mã hộ.
     * @queryParam residential_area_id integer Lọc theo tổ dân phố.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, head_name, household_code, member_count, created_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\HouseholdCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\Household paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->householdService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new HouseholdCollection($items));
    }

    /**
     * Chi tiết hộ gia đình
     *
     * @urlParam household integer required ID hộ gia đình. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\HouseholdResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Household
     * @apiResourceAdditional success=true
     */
    public function show(Household $household)
    {
        return $this->successResource(new HouseholdResource($this->householdService->show($household)));
    }

    /**
     * Tạo hộ gia đình
     *
     * @bodyParam head_name string required Tên chủ hộ. Example: Nguyễn Văn A
     * @bodyParam address string required Địa chỉ. Example: 12 Trần Phú, Hải Châu
     *
     * @apiResource App\Modules\Beneficiary\Resources\HouseholdResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\Household
     * @apiResourceAdditional success=true message="Tạo hộ gia đình thành công!"
     */
    public function store(StoreHouseholdRequest $request)
    {
        $item = $this->householdService->store($request->validated());

        return $this->successResource(new HouseholdResource($item), 'Tạo hộ gia đình thành công!', 201);
    }

    /**
     * Cập nhật hộ gia đình
     *
     * @urlParam household integer required ID hộ gia đình. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\HouseholdResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Household
     * @apiResourceAdditional success=true message="Cập nhật hộ gia đình thành công!"
     */
    public function update(UpdateHouseholdRequest $request, Household $household)
    {
        $item = $this->householdService->update($household, $request->validated());

        return $this->successResource(new HouseholdResource($item), 'Cập nhật hộ gia đình thành công!');
    }

    /**
     * Xóa hộ gia đình
     *
     * @urlParam household integer required ID hộ gia đình. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa hộ gia đình thành công!"}
     */
    public function destroy(Household $household)
    {
        $this->householdService->destroy($household);

        return $this->success(null, 'Xóa hộ gia đình thành công!');
    }

    /**
     * Xóa hàng loạt hộ gia đình
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyHouseholdRequest $request)
    {
        $this->householdService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Xuất Excel hộ gia đình
     *
     * Xuất ra các trường: id, household_code, head_name, head_id_number, residential_area, address,
     * phone, member_count, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Tìm theo tên chủ hộ hoặc mã hộ.
     */
    public function export(FilterRequest $request)
    {
        return $this->householdService->export($request->all());
    }

    /**
     * Import hộ gia đình
     *
     * Cột bắt buộc: head_name, address. Cột không bắt buộc: household_code, head_id_number, phone.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import hộ gia đình thành công."}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $this->householdService->import($request->file('file'));

        return $this->success(null, 'Import hộ gia đình thành công.');
    }
}
