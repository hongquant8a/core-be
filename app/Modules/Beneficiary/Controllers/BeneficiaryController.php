<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\BulkUpdateStatusBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\ChangeStatusBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\StoreBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\UpdateBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\UploadClassificationFilesRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryClassificationResource;
use App\Modules\Beneficiary\Resources\BeneficiaryCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryResource;
use App\Modules\Beneficiary\Services\BeneficiaryService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Người có công
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý hồ sơ người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt,
 * đổi trạng thái, xuất/nhập.
 */
class BeneficiaryController extends Controller
{
    public function __construct(private BeneficiaryService $beneficiaryService) {}

    /**
     * Thống kê người có công
     *
     * @queryParam search string Tìm theo họ tên / CCCD / SĐT của người có công, hoặc họ tên / CCCD / SĐT của thân nhân.
     * @queryParam status string Lọc theo trạng thái: pending, active, deceased, moved_out, suspended.
     * @queryParam type string Lọc theo loại đối tượng (BeneficiaryTypeEnum). Example: war_invalid
     * @queryParam residential_area_id integer Lọc theo tổ dân phố / thôn.
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
     * @queryParam search string Tìm theo họ tên / CCCD / SĐT của người có công, hoặc họ tên / CCCD / SĐT của thân nhân liên kết.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam type string Lọc theo loại đối tượng (BeneficiaryTypeEnum) — hồ sơ kiêm nhiều loại vẫn khớp. Example: war_invalid
     * @queryParam household_id integer Lọc theo hộ gia đình.
     * @queryParam residential_area_id integer Lọc theo tổ dân phố / thôn.
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
     * Hộ gia đình và tổ dân phố là **ID** (`household_id`, `residential_area_id`) — tạo trước qua
     * resource của nó. Thân nhân là mảng **liên kết** thân nhân có sẵn (`dependent_id` +
     * `relationship_type`), tài liệu và loại đối tượng là mảng thông tin đầy đủ.
     *
     * Mỗi hồ sơ chỉ được tối đa **1 thân nhân chính** (`dependents[].is_primary` = true) và tối đa
     * **1 loại đối tượng chính** (`classifications[].is_primary` = true) — gửi nhiều hơn sẽ báo lỗi 422.
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
     * `classifications` / `dependents` / `documents` là **trạng thái đầy đủ**: gửi mảng nào thì
     * THAY THẾ toàn bộ danh sách đó (xóa hết rồi tạo lại), không gửi khóa thì giữ nguyên, gửi `[]`
     * là xóa sạch. Không nhận `id` trong phần tử. Tập tin đính kèm của tài liệu cũ bị xóa theo.
     *
     * Mỗi hồ sơ chỉ được tối đa **1 thân nhân chính** (`dependents[].is_primary` = true) và tối đa
     * **1 loại đối tượng chính** (`classifications[].is_primary` = true) — gửi nhiều hơn sẽ báo lỗi 422.
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
     * @urlParam beneficiary integer required ID người có công. Example: 1
     *
     * @bodyParam status string required Trạng thái mới. Example: active
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
            $request->input('death_date'),
        );

        return $this->successResource(new BeneficiaryResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Đính kèm file quyết định công nhận cho một phân loại
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     * @urlParam classification integer required ID phân loại đối tượng. Example: 1
     * @bodyParam files file[] required Danh sách tập tin quyết định (nhiều file, mỗi file ≤ 10MB).
     *
     * @apiResource App\Modules\Beneficiary\Resources\BeneficiaryClassificationResource status=201
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryClassification
     * @apiResourceAdditional success=true message="Đính kèm file quyết định thành công!"
     */
    public function uploadClassificationFiles(UploadClassificationFilesRequest $request, Beneficiary $beneficiary, BeneficiaryClassification $classification)
    {
        abort_unless($classification->beneficiary_id === $beneficiary->id, 404);

        $item = $this->beneficiaryService->uploadClassificationFiles($classification, $request->file('files', []));

        return $this->successResource(new BeneficiaryClassificationResource($item), 'Đính kèm file quyết định thành công!', 201);
    }

    /**
     * Xóa một file quyết định của phân loại
     *
     * @urlParam beneficiary integer required ID người có công. Example: 1
     * @urlParam classification integer required ID phân loại đối tượng. Example: 1
     * @urlParam media integer required ID file (media). Example: 1
     *
     * @response 200 {"success": true, "message": "Xóa file quyết định thành công!"}
     */
    public function deleteClassificationFile(Beneficiary $beneficiary, BeneficiaryClassification $classification, int $media)
    {
        abort_unless($classification->beneficiary_id === $beneficiary->id, 404);

        $this->beneficiaryService->deleteClassificationFile($classification, $media);

        return $this->success(null, 'Xóa file quyết định thành công!');
    }

    /**
     * Xuất Excel người có công
     *
     * Xuất ra các trường: id, full_name, date_of_birth, birth_year, gender, id_number, head_id_number,
     * residential_area, status, death_date, address, latitude, longitude, phone, note, created_by,
     * updated_by, created_at, updated_at. Kèm 3 cột liệt kê chỉ để tham chiếu (import bỏ qua):
     * "Loại đối tượng", "Thân nhân" (dạng `Tên (Quan hệ)`), "Giấy tờ" — ngăn cách bởi `; `.
     *
     * @queryParam search string Tìm theo họ tên / CCCD / SĐT của người có công, hoặc của thân nhân liên kết.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam type string Lọc theo loại đối tượng (BeneficiaryTypeEnum). Example: war_invalid
     * @queryParam residential_area_id integer Lọc theo tổ dân phố / thôn.
     */
    public function export(FilterRequest $request)
    {
        return $this->beneficiaryService->export($request->all());
    }

    /**
     * Import người có công
     *
     * Cột bắt buộc: full_name, gender. Cột không bắt buộc: date_of_birth, birth_year, id_number, head_id_number (nhập CCCD chủ hộ), residential_area (nhập TÊN tổ dân phố, không khớp thì để trống), status (mặc định "pending"), death_date, address, latitude, longitude, phone, note. Giới tính/trạng thái nhận cả value gốc (male/pending) lẫn nhãn tiếng Việt.
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * Dòng lỗi validation được bỏ qua (các dòng hợp lệ vẫn import), trả về `failed_count` và
     * `errors` (số dòng, cột, thông báo, giá trị) cùng `error_file` (Excel tổng hợp lỗi dạng base64: STT, Hàng số, Cột, Lỗi, Giá trị) để cán bộ tải về, sửa và nhập lại.
     *
     * @response 200 {"success": true, "message": "Import người có công hoàn tất — đã bỏ qua 1 dòng lỗi, vui lòng kiểm tra và nhập lại các dòng này.", "data": {"failed_count": 1, "errors": [{"row": 3, "column": "Giới tính", "errors": ["Giới tính không được để trống."], "values": {"Họ tên": "Trần Văn B"}}]}}
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $failures = $this->beneficiaryService->import($request->file('file'));

        return $this->importResult($failures, 'người có công', \App\Modules\Beneficiary\Imports\BeneficiaryImport::FIELD_LABELS);
    }

    /**
     * Tải mẫu import người có công
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Beneficiary\Imports\BeneficiaryImport::TEMPLATE_LABELS, \App\Modules\Beneficiary\Imports\BeneficiaryImport::TEMPLATE_EXAMPLES, \App\Modules\Beneficiary\Imports\BeneficiaryImport::REQUIRED_KEYS, \App\Modules\Beneficiary\Imports\BeneficiaryImport::templateNotes(), \App\Modules\Beneficiary\Imports\BeneficiaryImport::templateOptions()),
            'import-beneficiaries-template.xlsx'
        );
    }
}
