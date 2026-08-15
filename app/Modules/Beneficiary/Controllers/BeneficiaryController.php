<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Exports\BeneficiaryExport;
use App\Modules\Beneficiary\Imports\BeneficiaryImport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\FilterBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Requests\SaveBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\SaveFullBeneficiaryRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryResource;
use App\Modules\Beneficiary\Services\BeneficiaryService;
use App\Modules\Core\Exports\ImportTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Beneficiary - Người có công
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý hồ sơ người có công: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa,
 * xóa hàng loạt, lưu trọn gói (save-full), xuất/nhập Excel.
 *
 * KHÔNG có `changeStatus` / `bulkUpdateStatus`: hồ sơ người có công không có trạng thái
 * nghiệp vụ. Chỉ ba bảng danh mục của module mới có.
 */
class BeneficiaryController extends Controller
{
    public function __construct(private readonly BeneficiaryService $service) {}

    /**
     * Thống kê người có công
     *
     * Không đếm theo trạng thái (module không có cột này) mà đếm theo danh mục và mức độ
     * đầy đủ dữ liệu — số hồ sơ thiếu toạ độ là thứ cán bộ cần biết để hoàn thiện bản đồ.
     *
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 120, "new_in_30_days": 8, "with_coordinates": 100, "without_coordinates": 20, "by_gender": {"male": 90, "female": 30}, "by_residential_area": {"Tổ dân phố 1": 40}, "by_type": {"Thương binh": 55}}}
     */
    public function stats(FilterBeneficiaryRequest $request)
    {
        return $this->success($this->service->stats($request->validated()));
    }

    /**
     * Danh sách người có công
     *
     * @queryParam search string Tìm theo họ tên, CCCD/CMND hoặc số điện thoại.
     * @queryParam residential_area_id integer Lọc theo tổ dân phố/thôn. Example: 3
     * @queryParam beneficiary_type_id integer Lọc theo loại đối tượng. Example: 2
     * @queryParam relationship_id integer Lọc hồ sơ có thân nhân với mối quan hệ này. Example: 1
     * @queryParam gender string Lọc theo giới tính: male, female, other. Example: male
     * @queryParam birth_year_from integer Năm sinh từ. Example: 1940
     * @queryParam birth_year_to integer Năm sinh đến. Example: 1960
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, full_name, birth_date, birth_year, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\Beneficiary paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterBeneficiaryRequest $request)
    {
        $items = $this->service->index($request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryCollection($items));
    }

    /**
     * Chi tiết người có công
     *
     * Trả kèm toàn bộ danh sách con (đối tượng, thân nhân, tài liệu) — dùng cho màn hình
     * form trọn gói.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function show(Beneficiary $beneficiary)
    {
        return $this->successResource(new BeneficiaryResource($this->service->show($beneficiary)));
    }

    /**
     * Tạo hồ sơ người có công
     *
     * Chỉ tạo bản chính. Cần tạo kèm danh sách con trong một request thì dùng save-full.
     *
     * Nhập lại CCCD của một hồ sơ đã xoá mềm sẽ KHÔI PHỤC hồ sơ đó thay vì tạo mới.
     */
    public function store(SaveBeneficiaryRequest $request)
    {
        $beneficiary = $this->service->store($request->validated());

        return $this->successResource(
            new BeneficiaryResource($beneficiary->load(['residentialArea', 'creator.media', 'editor.media'])),
            'Thêm hồ sơ người có công thành công'
        );
    }

    /**
     * Cập nhật hồ sơ người có công
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     *
     * @response 409 {"success": false, "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.", "error_code": "STALE_RECORD"}
     */
    public function update(SaveBeneficiaryRequest $request, Beneficiary $beneficiary)
    {
        $data = $request->validated();

        $updated = $this->service->update($beneficiary, $data, $data['lock_version'] ?? null);

        return $this->successResource(
            new BeneficiaryResource($updated->fresh(['residentialArea', 'creator.media', 'editor.media'])),
            'Cập nhật hồ sơ người có công thành công'
        );
    }

    /**
     * Xóa hồ sơ người có công
     *
     * Xóa mềm; dòng con giữ nguyên và khôi phục lại được cùng bản chính.
     *
     * @urlParam beneficiary integer required ID hồ sơ. Example: 1
     */
    public function destroy(Beneficiary $beneficiary)
    {
        $this->service->destroy($beneficiary);

        return $this->success(null, 'Xóa hồ sơ người có công thành công');
    }

    /**
     * Xóa hồ sơ người có công hàng loạt
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request)
    {
        $deleted = $this->service->bulkDestroy($request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa hồ sơ người có công thành công');
    }

    /**
     * Lưu trọn gói hồ sơ người có công
     *
     * Gộp bản chính + ba danh sách con (đối tượng, thân nhân, tài liệu) trong MỘT request.
     *
     * CHỈ dùng cho form trọn gói không phân trang: endpoint này xoá mềm mọi dòng con không
     * có trong payload. Màn hình có phân trang phải dùng sub-resource CRUD lẻ.
     *
     * Gửi `"[]"` cho một field JSON = xoá hết dòng con của quan hệ đó.
     * KHÔNG gửi field = giữ nguyên dữ liệu trong DB.
     *
     * Gọi bằng POST kèm multipart/form-data. Tệp gửi phẳng theo chỉ số dòng:
     * `type_relations_files[0][]`, `documents_files[0][]`.
     *
     * @urlParam beneficiary integer ID hồ sơ. Bỏ trống để tạo mới. Example: 1
     *
     * @response 409 {"success": false, "message": "Bản ghi đã được người khác cập nhật. Vui lòng tải lại trang.", "error_code": "STALE_RECORD"}
     */
    public function saveFull(SaveFullBeneficiaryRequest $request, ?Beneficiary $beneficiary = null)
    {
        return $this->successResource(
            new BeneficiaryResource($this->service->saveFull($beneficiary, $request->validated())),
            'Lưu hồ sơ thành công'
        );
    }

    /**
     * Xuất danh sách người có công
     *
     * Xuất ra các trường: id, họ và tên, ngày sinh, năm sinh, giới tính, CCCD/CMND,
     * số điện thoại, tổ dân phố/thôn, địa chỉ, vĩ độ, kinh độ, ghi chú, danh sách loại
     * đối tượng, danh sách thân nhân, danh sách tài liệu, created_by, updated_by,
     * created_at, updated_at.
     *
     * Ba cột "Danh sách ..." chỉ để đọc đối chiếu — import bỏ qua.
     *
     * @queryParam search string Tìm theo họ tên, CCCD/CMND hoặc số điện thoại.
     * @queryParam residential_area_id integer Lọc theo tổ dân phố/thôn. Example: 3
     * @queryParam beneficiary_type_id integer Lọc theo loại đối tượng. Example: 2
     */
    public function export(FilterBeneficiaryRequest $request)
    {
        return Excel::download(
            new BeneficiaryExport($this->service->exportQuery($request->validated())),
            'danh-sach-nguoi-co-cong.xlsx'
        );
    }

    /**
     * Nhập danh sách người có công từ Excel
     *
     * Cột bắt buộc: Họ và tên. Cột không bắt buộc: ngày sinh, năm sinh, giới tính
     * (mặc định trống), CCCD/CMND, số điện thoại, tổ dân phố/thôn (nhập TÊN, không khớp
     * thì để trống), địa chỉ, vĩ độ, kinh độ, ghi chú.
     *
     * Dòng lỗi được bỏ qua, dòng hợp lệ vẫn nhập. Chi tiết lỗi trả về trong
     * `data.error_file` dưới dạng file Excel base64.
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $import = new BeneficiaryImport;
        Excel::import($import, $request->file('file'));

        return $this->importResult($import->failures(), 'người có công', BeneficiaryImport::FIELD_LABELS);
    }

    /**
     * Tải file mẫu nhập người có công
     */
    public function importTemplate()
    {
        return Excel::download(
            new ImportTemplateExport(
                BeneficiaryImport::TEMPLATE_LABELS,
                BeneficiaryImport::TEMPLATE_EXAMPLES,
                BeneficiaryImport::REQUIRED_KEYS,
                BeneficiaryImport::templateNotes(),
                BeneficiaryImport::templateOptions(),
            ),
            'import-nguoi-co-cong-template.xlsx'
        );
    }
}
