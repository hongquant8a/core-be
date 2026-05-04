<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\PublicOptionResource;
use App\Modules\Meeting\Models\MeetingDocumentType;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;

/**
 * @group Meeting - Danh mục loại tài liệu họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý loại tài liệu họp: public, thống kê, danh sách, chi tiết, tạo, cập nhật, xóa và trạng thái.
 */
class MeetingDocumentTypeController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    /**
     * Danh sách loại tài liệu họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: tài liệu chính
     */
    public function public(FilterRequest $request)
    {
        $items = $this->catalogService->publicCatalog(MeetingDocumentType::class, $request->all());

        return $this->successCollection(CatalogResource::collection($items));
    }

    /**
     * Danh sách loại tài liệu họp công khai cho dropdown.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: tài liệu chính
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->catalogService->publicOptions(MeetingDocumentType::class, $request->all());

        return $this->successCollection(PublicOptionResource::collection($items));
    }

    /**
     * Thống kê loại tài liệu họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: tài liệu chính
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats(MeetingDocumentType::class, $request->all()));
    }

    /**
     * Danh sách loại tài liệu họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: tài liệu chính
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index(MeetingDocumentType::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    /**
     * Chi tiết loại tài liệu họp.
     *
     * @urlParam meetingDocumentType integer required ID loại tài liệu họp. Example: 1
     */
    public function show(MeetingDocumentType $meetingDocumentType)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($meetingDocumentType)));
    }

    /**
     * Tạo loại tài liệu họp.
     *
     * @bodyParam name string required Tên loại tài liệu họp. Example: Tài liệu trình bày
     * @bodyParam description string Mô tả loại tài liệu họp. Example: Tài liệu dùng để trình chiếu
     * @bodyParam status string Trạng thái danh mục. Example: active
     */
    public function store(StoreCatalogRequest $request)
    {
        $item = $this->catalogService->store(MeetingDocumentType::class, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Tạo loại tài liệu họp thành công!', 201);
    }

    /**
     * Cập nhật loại tài liệu họp.
     *
     * @urlParam meetingDocumentType integer required ID loại tài liệu họp. Example: 1
     * @bodyParam name string Tên loại tài liệu họp. Example: Tài liệu tham khảo
     * @bodyParam description string Mô tả loại tài liệu họp. Example: Tài liệu đọc thêm
     * @bodyParam status string Trạng thái danh mục. Example: inactive
     */
    public function update(UpdateCatalogRequest $request, MeetingDocumentType $meetingDocumentType)
    {
        $item = $this->catalogService->update($meetingDocumentType, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Cập nhật loại tài liệu họp thành công!');
    }

    /**
     * Xóa loại tài liệu họp.
     *
     * @urlParam meetingDocumentType integer required ID loại tài liệu họp. Example: 1
     */
    public function destroy(MeetingDocumentType $meetingDocumentType)
    {
        $this->catalogService->destroy($meetingDocumentType);

        return $this->success(null, 'Xóa loại tài liệu họp thành công!');
    }

    /**
     * Xóa hàng loạt loại tài liệu họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy(MeetingDocumentType::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt loại tài liệu họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần cập nhật trạng thái. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus(MeetingDocumentType::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái loại tài liệu họp.
     *
     * @urlParam meetingDocumentType integer required ID loại tài liệu họp. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingDocumentType $meetingDocumentType)
    {
        $item = $this->catalogService->changeStatus($meetingDocumentType, $request->status);

        return $this->successResource(new CatalogResource($item), 'Đổi trạng thái thành công!');
    }
}
