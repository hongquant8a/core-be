<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Exports\BeneficiaryCatalogExport;
use App\Modules\Beneficiary\Imports\BeneficiaryCatalogImport;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\Catalog\BulkUpdateCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ChangeCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ReorderCatalogRequest;
use App\Modules\Beneficiary\Requests\Catalog\SaveBeneficiaryTypeRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryTypeCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryTypeResource;
use App\Modules\Beneficiary\Services\BeneficiaryCatalogService;
use App\Modules\Core\Exports\ImportTemplateExport;
use App\Modules\Core\Requests\FilterRequest;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Beneficiary - Danh mục Loại đối tượng
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục tenant-scoped (Thương binh, Bệnh binh, Thân nhân liệt sĩ...). Ở v1 đây là enum
 * cứng — chuyển thành danh mục DB để thêm loại mới không phải deploy code.
 */
class BeneficiaryTypeController extends Controller
{
    private const MODEL = BeneficiaryType::class;

    public function __construct(private readonly BeneficiaryCatalogService $service) {}

    /**
     * Thống kê loại đối tượng
     *
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 12, "active": 10, "inactive": 2}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->service->stats(self::MODEL, $request->validated()));
    }

    /**
     * Danh sách loại đối tượng
     *
     * KHÔNG tự lọc theo trạng thái. FE dựng dropdown phải truyền `status=active`.
     *
     * @queryParam search string Tìm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive. Example: active
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, sort_order, created_at, updated_at. Example: sort_order
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryTypeCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryType paginate=10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index(self::MODEL, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryTypeCollection($items));
    }

    /**
     * Chi tiết loại đối tượng
     *
     * @urlParam beneficiaryType integer required ID loại đối tượng. Example: 1
     */
    public function show(BeneficiaryType $beneficiaryType)
    {
        return $this->successResource(new BeneficiaryTypeResource($this->service->show($beneficiaryType)));
    }

    /**
     * Thêm loại đối tượng
     *
     * Nhập lại đúng tên một mục đã xoá mềm sẽ KHÔI PHỤC mục đó thay vì tạo mới.
     */
    public function store(SaveBeneficiaryTypeRequest $request)
    {
        $item = $this->service->store(self::MODEL, $request->validated());

        return $this->successResource(new BeneficiaryTypeResource($item), 'Thêm loại đối tượng thành công');
    }

    /**
     * Cập nhật loại đối tượng
     *
     * @urlParam beneficiaryType integer required ID loại đối tượng. Example: 1
     */
    public function update(SaveBeneficiaryTypeRequest $request, BeneficiaryType $beneficiaryType)
    {
        $item = $this->service->update($beneficiaryType, $request->validated());

        return $this->successResource(new BeneficiaryTypeResource($item), 'Cập nhật loại đối tượng thành công');
    }

    /**
     * Xóa loại đối tượng
     *
     * Bị chặn 409 nếu đang có hồ sơ gán loại này.
     *
     * @urlParam beneficiaryType integer required ID loại đối tượng. Example: 1
     *
     * @response 409 {"success": false, "message": "Không thể xoá \"Thương binh\" vì đang có 55 bản ghi sử dụng. Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái \"Ngừng sử dụng\".", "error_code": "CATALOG_IN_USE"}
     */
    public function destroy(BeneficiaryType $beneficiaryType)
    {
        $this->service->destroy($beneficiaryType);

        return $this->success(null, 'Xóa loại đối tượng thành công');
    }

    /**
     * Xóa loại đối tượng hàng loạt
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request)
    {
        $deleted = $this->service->bulkDestroy(self::MODEL, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa loại đối tượng thành công');
    }

    /**
     * Cập nhật trạng thái loại đối tượng hàng loạt
     */
    public function bulkUpdateStatus(BulkUpdateCatalogStatusRequest $request)
    {
        $data = $request->validated();
        $updated = $this->service->bulkUpdateStatus(self::MODEL, $data['ids'], $data['status']);

        return $this->success(['updated' => $updated], 'Cập nhật trạng thái thành công');
    }

    /**
     * Đổi trạng thái một loại đối tượng
     *
     * @urlParam beneficiaryType integer required ID loại đối tượng. Example: 1
     */
    public function changeStatus(ChangeCatalogStatusRequest $request, BeneficiaryType $beneficiaryType)
    {
        $item = $this->service->changeStatus($beneficiaryType, $request->validated()['status']);

        return $this->successResource(new BeneficiaryTypeResource($item), 'Cập nhật trạng thái thành công');
    }

    /**
     * Sắp xếp lại thứ tự loại đối tượng
     */
    public function reorder(ReorderCatalogRequest $request)
    {
        $updated = $this->service->reorder(self::MODEL, $request->validated()['items']);

        return $this->success(['updated' => $updated], 'Sắp xếp lại thành công');
    }

    /**
     * Xuất danh mục loại đối tượng
     *
     * Xuất ra các trường: id, tên, ghi chú, thứ tự, trạng thái, số bản ghi đang dùng,
     * created_by, updated_by, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return Excel::download(
            new BeneficiaryCatalogExport(
                $this->service->exportQuery(self::MODEL, $request->validated()),
                'loại đối tượng',
            ),
            'danh-muc-loai-doi-tuong.xlsx'
        );
    }

    /**
     * Nhập danh mục loại đối tượng từ Excel
     *
     * Cột bắt buộc: Tên. Cột không bắt buộc: Ghi chú, Thứ tự (mặc định 0),
     * Trạng thái (mặc định active).
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $import = new BeneficiaryCatalogImport(self::MODEL);
        Excel::import($import, $request->file('file'));

        return $this->importResult($import->failures(), 'loại đối tượng', BeneficiaryCatalogImport::FIELD_LABELS);
    }

    /**
     * Tải file mẫu nhập danh mục loại đối tượng
     */
    public function importTemplate()
    {
        return Excel::download(
            new ImportTemplateExport(
                BeneficiaryCatalogImport::TEMPLATE_LABELS,
                BeneficiaryCatalogImport::TEMPLATE_EXAMPLES,
                BeneficiaryCatalogImport::REQUIRED_KEYS,
                BeneficiaryCatalogImport::templateNotes(),
                BeneficiaryCatalogImport::templateOptions(),
            ),
            'import-danh-muc-loai-doi-tuong-template.xlsx'
        );
    }
}
