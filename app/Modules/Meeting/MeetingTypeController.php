<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\PublicOptionResource;
use App\Modules\Meeting\Models\MeetingType;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;

/**
 * @group Meeting - Danh mục loại cuộc họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý loại cuộc họp: public, thống kê, danh sách, chi tiết, tạo, cập nhật, xóa và trạng thái.
 */
class MeetingTypeController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    /**
     * Danh sách loại cuộc họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: giao ban
     */
    public function public(FilterRequest $request)
    {
        $items = $this->catalogService->publicCatalog(MeetingType::class, $request->all());

        return $this->successCollection(CatalogResource::collection($items));
    }

    /**
     * Danh sách loại cuộc họp công khai cho dropdown.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: giao ban
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->catalogService->publicOptions(MeetingType::class, $request->all());

        return $this->successCollection(PublicOptionResource::collection($items));
    }

    /**
     * Thống kê loại cuộc họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: giao ban
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats(MeetingType::class, $request->all()));
    }

    /**
     * Danh sách loại cuộc họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: giao ban
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index(MeetingType::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    /**
     * Chi tiết loại cuộc họp.
     *
     * @urlParam meetingType integer required ID loại cuộc họp. Example: 1
     */
    public function show(MeetingType $meetingType)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($meetingType)));
    }

    /**
     * Tạo loại cuộc họp.
     *
     * @bodyParam name string required Tên loại cuộc họp. Example: Họp giao ban
     * @bodyParam description string Mô tả loại cuộc họp. Example: Họp giao ban định kỳ tuần
     * @bodyParam status string Trạng thái danh mục. Example: active
     */
    public function store(StoreCatalogRequest $request)
    {
        $item = $this->catalogService->store(MeetingType::class, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Tạo loại cuộc họp thành công!', 201);
    }

    /**
     * Cập nhật loại cuộc họp.
     *
     * @urlParam meetingType integer required ID loại cuộc họp. Example: 1
     * @bodyParam name string Tên loại cuộc họp. Example: Họp chuyên đề
     * @bodyParam description string Mô tả loại cuộc họp. Example: Họp chuyên đề theo lĩnh vực
     * @bodyParam status string Trạng thái danh mục. Example: inactive
     */
    public function update(UpdateCatalogRequest $request, MeetingType $meetingType)
    {
        $item = $this->catalogService->update($meetingType, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Cập nhật loại cuộc họp thành công!');
    }

    /**
     * Xóa loại cuộc họp.
     *
     * @urlParam meetingType integer required ID loại cuộc họp. Example: 1
     */
    public function destroy(MeetingType $meetingType)
    {
        $this->catalogService->destroy($meetingType);

        return $this->success(null, 'Xóa loại cuộc họp thành công!');
    }

    /**
     * Xóa hàng loạt loại cuộc họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy(MeetingType::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt loại cuộc họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần cập nhật trạng thái. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus(MeetingType::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái loại cuộc họp.
     *
     * @urlParam meetingType integer required ID loại cuộc họp. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingType $meetingType)
    {
        $item = $this->catalogService->changeStatus($meetingType, $request->status);

        return $this->successResource(new CatalogResource($item), 'Đổi trạng thái thành công!');
    }
}
