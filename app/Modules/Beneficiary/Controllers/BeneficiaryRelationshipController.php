<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Exports\BeneficiaryCatalogExport;
use App\Modules\Beneficiary\Imports\BeneficiaryCatalogImport;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use App\Modules\Beneficiary\Requests\BulkDestroyBeneficiaryRequest;
use App\Modules\Beneficiary\Requests\Catalog\BulkUpdateCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ChangeCatalogStatusRequest;
use App\Modules\Beneficiary\Requests\Catalog\ReorderCatalogRequest;
use App\Modules\Beneficiary\Requests\Catalog\SaveRelationshipRequest;
use App\Modules\Beneficiary\Requests\ImportBeneficiaryFileRequest;
use App\Modules\Beneficiary\Resources\BeneficiaryRelationshipCollection;
use App\Modules\Beneficiary\Resources\BeneficiaryRelationshipResource;
use App\Modules\Beneficiary\Services\BeneficiaryCatalogService;
use App\Modules\Core\Exports\ImportTemplateExport;
use App\Modules\Core\Requests\FilterRequest;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Beneficiary - Danh mục Mối quan hệ
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Danh mục tenant-scoped mô tả quan hệ giữa thân nhân và người có công (Vợ, Chồng, Con, Bố,
 * Mẹ...). Ở v1 đây là enum cứng và đã phải viết riêng một migration chỉ để tách `spouse`
 * thành `wife`/`husband` — chuyển thành danh mục DB để cán bộ tự thêm.
 */
class BeneficiaryRelationshipController extends Controller
{
    private const MODEL = BeneficiaryRelationship::class;

    public function __construct(private readonly BeneficiaryCatalogService $service) {}

    /**
     * Thống kê mối quan hệ
     *
     * @queryParam from_date date Lọc từ ngày tạo. Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo. Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 8, "active": 8, "inactive": 0}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->service->stats(self::MODEL, $request->validated()));
    }

    /**
     * Danh sách mối quan hệ
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
     * @apiResourceCollection App\Modules\Beneficiary\Resources\BeneficiaryRelationshipCollection
     *
     * @apiResourceModel App\Modules\Beneficiary\Models\BeneficiaryRelationship paginate=10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index(self::MODEL, $request->validated(), (int) ($request->limit ?? 10));

        return $this->successCollection(new BeneficiaryRelationshipCollection($items));
    }

    /**
     * Chi tiết mối quan hệ
     *
     * @urlParam relationship integer required ID mối quan hệ. Example: 1
     */
    public function show(BeneficiaryRelationship $relationship)
    {
        return $this->successResource(new BeneficiaryRelationshipResource($this->service->show($relationship)));
    }

    /**
     * Thêm mối quan hệ
     *
     * Nhập lại đúng tên một mục đã xoá mềm sẽ KHÔI PHỤC mục đó thay vì tạo mới.
     */
    public function store(SaveRelationshipRequest $request)
    {
        $item = $this->service->store(self::MODEL, $request->validated());

        return $this->successResource(new BeneficiaryRelationshipResource($item), 'Thêm mối quan hệ thành công');
    }

    /**
     * Cập nhật mối quan hệ
     *
     * @urlParam relationship integer required ID mối quan hệ. Example: 1
     */
    public function update(SaveRelationshipRequest $request, BeneficiaryRelationship $relationship)
    {
        $item = $this->service->update($relationship, $request->validated());

        return $this->successResource(new BeneficiaryRelationshipResource($item), 'Cập nhật mối quan hệ thành công');
    }

    /**
     * Xóa mối quan hệ
     *
     * Bị chặn 409 nếu đang có thân nhân dùng mối quan hệ này.
     *
     * @urlParam relationship integer required ID mối quan hệ. Example: 1
     *
     * @response 409 {"success": false, "message": "Không thể xoá \"Con\" vì đang có 30 bản ghi sử dụng. Nếu chỉ muốn ẩn khỏi danh sách chọn khi nhập hồ sơ mới, hãy chuyển sang trạng thái \"Ngừng sử dụng\".", "error_code": "CATALOG_IN_USE"}
     */
    public function destroy(BeneficiaryRelationship $relationship)
    {
        $this->service->destroy($relationship);

        return $this->success(null, 'Xóa mối quan hệ thành công');
    }

    /**
     * Xóa mối quan hệ hàng loạt
     */
    public function bulkDestroy(BulkDestroyBeneficiaryRequest $request)
    {
        $deleted = $this->service->bulkDestroy(self::MODEL, $request->validated()['ids']);

        return $this->success(['deleted' => $deleted], 'Xóa mối quan hệ thành công');
    }

    /**
     * Cập nhật trạng thái mối quan hệ hàng loạt
     */
    public function bulkUpdateStatus(BulkUpdateCatalogStatusRequest $request)
    {
        $data = $request->validated();
        $updated = $this->service->bulkUpdateStatus(self::MODEL, $data['ids'], $data['status']);

        return $this->success(['updated' => $updated], 'Cập nhật trạng thái thành công');
    }

    /**
     * Đổi trạng thái một mối quan hệ
     *
     * @urlParam relationship integer required ID mối quan hệ. Example: 1
     */
    public function changeStatus(ChangeCatalogStatusRequest $request, BeneficiaryRelationship $relationship)
    {
        $item = $this->service->changeStatus($relationship, $request->validated()['status']);

        return $this->successResource(new BeneficiaryRelationshipResource($item), 'Cập nhật trạng thái thành công');
    }

    /**
     * Sắp xếp lại thứ tự mối quan hệ
     */
    public function reorder(ReorderCatalogRequest $request)
    {
        $updated = $this->service->reorder(self::MODEL, $request->validated()['items']);

        return $this->success(['updated' => $updated], 'Sắp xếp lại thành công');
    }

    /**
     * Xuất danh mục mối quan hệ
     *
     * Xuất ra các trường: id, tên, ghi chú, thứ tự, trạng thái, số bản ghi đang dùng,
     * created_by, updated_by, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return Excel::download(
            new BeneficiaryCatalogExport(
                $this->service->exportQuery(self::MODEL, $request->validated()),
                'mối quan hệ',
            ),
            'danh-muc-moi-quan-he.xlsx'
        );
    }

    /**
     * Nhập danh mục mối quan hệ từ Excel
     *
     * Cột bắt buộc: Tên. Cột không bắt buộc: Ghi chú, Thứ tự (mặc định 0),
     * Trạng thái (mặc định active).
     */
    public function import(ImportBeneficiaryFileRequest $request)
    {
        $import = new BeneficiaryCatalogImport(self::MODEL);
        Excel::import($import, $request->file('file'));

        return $this->importResult($import->failures(), 'mối quan hệ', BeneficiaryCatalogImport::FIELD_LABELS);
    }

    /**
     * Tải file mẫu nhập danh mục mối quan hệ
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
            'import-danh-muc-moi-quan-he-template.xlsx'
        );
    }
}
