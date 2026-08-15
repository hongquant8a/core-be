<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Exports\BeneficiaryCatalogExport;
use App\Modules\Beneficiary\Imports\BeneficiaryCatalogImport;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\Catalog\BulkUpdateCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ChangeCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ReorderCatalogRequest;
use App\Modules\Beneficiary\Requests\Catalog\SaveResidentialAreaRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryResidentialAreaCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryResidentialAreaResource;
use App\Modules\Beneficiary\Services\BeneficiaryCatalogService;
use App\Modules\Core\Exports\ImportTemplateExport;
use App\Modules\Core\Requests\FilterRequest;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Beneficiary - Danh mục Tổ dân phố/Thôn
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục tenant-scoped, cán bộ tự quản trị. Được tham chiếu từ cả hồ sơ người có công lẫn
 * thân nhân bằng `restrictOnDelete` — xoá mục đang dùng bị chặn 409, thay vào đó chuyển sang
 * trạng thái "Ngừng sử dụng".
 *
 * Logic nằm ở `BeneficiaryCatalogService` dùng chung cho cả ba danh mục; controller này chỉ
 * buộc model, Resource và nhãn tiếng Việt vào bộ action đó.
 */
class BeneficiaryResidentialAreaController extends Controller
{
    private const MODEL = BeneficiaryResidentialArea::class;

    public function __construct(private readonly BeneficiaryCatalogService $service) {}

    /**
     * Thống kê tổ dân phố/thôn
     *
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 25, "active": 22, "inactive": 3}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->service->stats(self::MODEL, $request->validated()));
    }

    /**
     * Danh sách tổ dân phố/thôn
     *
     * KHÔNG tự lọc theo trạng thái: màn quản trị cần thấy cả mục đã ngừng dùng. FE dựng
     * dropdown phải truyền `status=active` tường minh.
     *
     * @queryParam search string Tìm theo tên.
     * @queryParam status string Lọc theo trạng thái: active, inactive. Example: active
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, sort_order, created_at, updated_at. Mặc định sort_order rồi name. Example: sort_order
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang, -1 = không phân trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryResidentialAreaCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryResidentialArea paginate=10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index(self::MODEL, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryResidentialAreaCollection($items));
    }

    /**
     * Chi tiết tổ dân phố/thôn
     *
     * @urlParam residentialArea integer required ID tổ dân phố/thôn. Example: 1
     */
    public function show(BeneficiaryResidentialArea $residentialArea)
    {
        return $this->successResource(new BeneficiaryResidentialAreaResource($this->service->show($residentialArea)));
    }

    /**
     * Thêm tổ dân phố/thôn
     *
     * Nhập lại đúng tên một mục đã xoá mềm sẽ KHÔI PHỤC mục đó thay vì tạo mới — `name` là
     * định danh duy nhất nên UNIQUE index vẫn giữ chỗ của dòng đã xoá.
     */
    public function store(SaveResidentialAreaRequest $request)
    {
        $item = $this->service->store(self::MODEL, $request->validated());

        return $this->successResource(
            new BeneficiaryResidentialAreaResource($item),
            'Thêm tổ dân phố/thôn thành công'
        );
    }

    /**
     * Cập nhật tổ dân phố/thôn
     *
     * @urlParam residentialArea integer required ID tổ dân phố/thôn. Example: 1
     */
    public function update(SaveResidentialAreaRequest $request, BeneficiaryResidentialArea $residentialArea)
    {
        $item = $this->service->update($residentialArea, $request->validated());

        return $this->successResource(
            new BeneficiaryResidentialAreaResource($item),
            'Cập nhật tổ dân phố/thôn thành công'
        );
    }

    /**
     * Xóa tổ dân phố/thôn
     *
     * Bị chặn 409 nếu đang có hồ sơ hoặc thân nhân tham chiếu. Muốn ẩn khỏi danh sách chọn
     * mà giữ dữ liệu cũ thì chuyển trạng thái sang "Ngừng sử dụng".
     *
     * @urlParam residentialArea integer required ID tổ dân phố/thôn. Example: 1
     *
     * @response 409 {"success": false, "message": "Không thể xoá \"Tổ dân phố 5\" vì đang có 12 bản ghi sử dụng. Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái \"Ngừng sử dụng\".", "error_code": "CATALOG_IN_USE"}
     */
    public function destroy(BeneficiaryResidentialArea $residentialArea)
    {
        $this->service->destroy($residentialArea);

        return $this->success(null, 'Xóa tổ dân phố/thôn thành công');
    }

    /**
     * Xóa tổ dân phố/thôn hàng loạt
     *
     * Chỉ cần một mục đang được dùng là cả lô bị chặn 409 — xoá danh mục là thao tác quản
     * trị hiếm, xoá nửa vời khó lần lại hơn là làm lại từ đầu.
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request)
    {
        $deleted = $this->service->bulkDestroy(self::MODEL, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa tổ dân phố/thôn thành công');
    }

    /**
     * Cập nhật trạng thái tổ dân phố/thôn hàng loạt
     */
    public function bulkUpdateStatus(BulkUpdateCatalogStatusRequest $request)
    {
        $data = $request->validated();
        $updated = $this->service->bulkUpdateStatus(self::MODEL, $data['ids'], $data['status']);

        return $this->success(['updated' => $updated], 'Cập nhật trạng thái thành công');
    }

    /**
     * Đổi trạng thái một tổ dân phố/thôn
     *
     * @urlParam residentialArea integer required ID tổ dân phố/thôn. Example: 1
     */
    public function changeStatus(ChangeCatalogStatusRequest $request, BeneficiaryResidentialArea $residentialArea)
    {
        $item = $this->service->changeStatus($residentialArea, $request->validated()['status']);

        return $this->successResource(
            new BeneficiaryResidentialAreaResource($item),
            'Cập nhật trạng thái thành công'
        );
    }

    /**
     * Sắp xếp lại thứ tự tổ dân phố/thôn
     */
    public function reorder(ReorderCatalogRequest $request)
    {
        $updated = $this->service->reorder(self::MODEL, $request->validated()['items']);

        return $this->success(['updated' => $updated], 'Sắp xếp lại thành công');
    }

    /**
     * Xuất danh mục tổ dân phố/thôn
     *
     * Xuất ra các trường: id, tên, ghi chú, thứ tự, trạng thái, số bản ghi đang dùng,
     * created_by, updated_by, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return Excel::download(
            new BeneficiaryCatalogExport(
                $this->service->exportQuery(self::MODEL, $request->validated()),
                'tổ dân phố/thôn',
            ),
            'danh-muc-to-dan-pho.xlsx'
        );
    }

    /**
     * Nhập danh mục tổ dân phố/thôn từ Excel
     *
     * Cột bắt buộc: Tên. Cột không bắt buộc: Ghi chú, Thứ tự (mặc định 0),
     * Trạng thái (mặc định active).
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $import = new BeneficiaryCatalogImport(self::MODEL);
        Excel::import($import, $request->file('file'));

        return $this->importResult($import->failures(), 'tổ dân phố/thôn', BeneficiaryCatalogImport::FIELD_LABELS);
    }

    /**
     * Tải file mẫu nhập danh mục tổ dân phố/thôn
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
            'import-danh-muc-to-dan-pho-template.xlsx'
        );
    }
}
