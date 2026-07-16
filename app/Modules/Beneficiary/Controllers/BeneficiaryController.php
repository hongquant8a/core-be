<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\BulkUpdateStatusBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\ChangeStatusBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\UpdateBeneficiaryRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryResource;
use App\Modules\Beneficiary\Resources\StatusHistoryCollection;
use App\Modules\Beneficiary\Services\BeneficiaryService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Người có công
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý hồ sơ người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt,
 * đổi trạng thái, xuất/nhập, lịch sử thay đổi trạng thái.
 */
class BeneficiaryController extends Controller
{
    public function __construct(private BeneficiaryService $beneficiaryService) {}

    /**
     * Thống kê người có công
     *
     * @queryParam search string Tìm theo họ tên hoặc CCCD.
     * @queryParam status string Lọc theo trạng thái: pending, active, deceased, moved_out, suspended.
     *
     * @response 200 {"success": true, "data": {"total": 50, "pending": 5, "active": 40, "deceased": 5}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->beneficiaryService->stats($request->all()));
    }

    /**
     * Danh sách người có công
     *
     * @queryParam search string Tìm theo họ tên hoặc CCCD.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam household_id integer Lọc theo hộ gia đình.
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d).
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d).
     * @queryParam sort_by string Sắp xếp theo: id, full_name, date_of_birth, status, created_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->beneficiaryService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryCollection($items));
    }

    /**
     * Chi tiết người có công
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary
     * @apiResourceAdditional success=true
     */
    public function show(Beneficiary $beneficiary)
    {
        return $this->successResource(new BeneficiaryResource($this->beneficiaryService->show($beneficiary)));
    }

    /**
     * Tạo hồ sơ người có công
     *
     * @bodyParam full_name string required Họ tên. Example: Trần Văn B
     * @bodyParam gender string required Giới tính: male, female, other. Example: male
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary
     * @apiResourceAdditional success=true message="Tạo hồ sơ người có công thành công!"
     */
    public function store(StoreBeneficiaryRequest $request)
    {
        $item = $this->beneficiaryService->store($request->validated());

        return $this->successResource(new BeneficiaryResource($item), 'Tạo hồ sơ người có công thành công!', 201);
    }

    /**
     * Cập nhật hồ sơ người có công
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary
     * @apiResourceAdditional success=true message="Cập nhật hồ sơ người có công thành công!"
     */
    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $item = $this->beneficiaryService->update($beneficiary, $request->validated());

        return $this->successResource(new BeneficiaryResource($item), 'Cập nhật hồ sơ người có công thành công!');
    }

    /**
     * Xóa hồ sơ người có công
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa hồ sơ người có công thành công!"}
     */
    public function destroy(Beneficiary $beneficiary)
    {
        $this->beneficiaryService->destroy($beneficiary);

        return $this->success(null, 'Xóa hồ sơ người có công thành công!');
    }

    /**
     * Xóa hàng loạt người có công
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Xóa hàng loạt thành công!"}
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request)
    {
        $this->beneficiaryService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt người có công
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: suspended
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusBeneficiaryRequest $request)
    {
        $this->beneficiaryService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái người có công
     *
     * Ghi lịch sử vào beneficiary_status_histories. Nếu chuyển deceased/moved_out, tự động
     * dừng các khoản trợ cấp đang active gắn trực tiếp với người có công này.
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     *
     * @bodyParam status string required Trạng thái mới. Example: active
     * @bodyParam reason string Lý do đổi trạng thái.
     * @bodyParam death_date date Ngày mất (bắt buộc nếu status = deceased).
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryResource
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $item = $this->beneficiaryService->changeStatus(
            $beneficiary,
            $request->status,
            $request->input('reason'),
            $request->input('death_date'),
        );

        return $this->successResource(new BeneficiaryResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Lịch sử thay đổi trạng thái
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     * @queryParam from_date date Lọc từ ngày (Y-m-d).
     * @queryParam to_date date Lọc đến ngày (Y-m-d).
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\StatusHistoryCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\StatusHistory paginate=10
     * @apiResourceAdditional success=true
     */
    public function statusHistories(FilterRequest $request, Beneficiary $beneficiary)
    {
        $items = $this->beneficiaryService->statusHistories($beneficiary, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new StatusHistoryCollection($items));
    }

    /**
     * Xuất Excel người có công
     *
     * Xuất ra các trường: id, full_name, date_of_birth, gender, id_number, household_code, status,
     * created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Tìm theo họ tên hoặc CCCD.
     * @queryParam status string Lọc theo trạng thái.
     */
    public function export(FilterRequest $request)
    {
        return $this->beneficiaryService->export($request->all());
    }

    /**
     * Import người có công
     *
     * Cột bắt buộc: full_name, gender. Cột không bắt buộc: date_of_birth, id_number, status (mặc định "pending").
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import người có công thành công."}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $this->beneficiaryService->import($request->file('file'));

        return $this->success(null, 'Import người có công thành công.');
    }
}
