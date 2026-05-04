<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\PublicOptionResource;
use App\Modules\Meeting\Models\MeetingLocation;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;

/**
 * @group Meeting - Danh mục địa điểm họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý địa điểm họp: public, thống kê, danh sách, chi tiết, tạo, cập nhật, xóa và trạng thái.
 */
class MeetingLocationController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    /**
     * Danh sách địa điểm họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: hội trường
     */
    public function public(FilterRequest $request)
    {
        $items = $this->catalogService->publicCatalog(MeetingLocation::class, $request->all());

        return $this->successCollection(CatalogResource::collection($items));
    }

    /**
     * Danh sách địa điểm họp công khai cho dropdown.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: hội trường
     */
    public function publicOptions(FilterRequest $request)
    {
        $items = $this->catalogService->publicOptions(MeetingLocation::class, $request->all());

        return $this->successCollection(PublicOptionResource::collection($items));
    }

    /**
     * Thống kê địa điểm họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: hội trường
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats(MeetingLocation::class, $request->all()));
    }

    /**
     * Danh sách địa điểm họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên. Example: hội trường
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index(MeetingLocation::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    /**
     * Chi tiết địa điểm họp.
     *
     * @urlParam meetingLocation integer required ID địa điểm họp. Example: 1
     */
    public function show(MeetingLocation $meetingLocation)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($meetingLocation)));
    }

    /**
     * Tạo địa điểm họp.
     *
     * @bodyParam name string required Tên địa điểm họp. Example: Hội trường tầng 3
     * @bodyParam description string Mô tả địa điểm họp. Example: Sức chứa 200 người
     * @bodyParam status string Trạng thái danh mục. Example: active
     */
    public function store(StoreCatalogRequest $request)
    {
        $item = $this->catalogService->store(MeetingLocation::class, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Tạo địa điểm cuộc họp thành công!', 201);
    }

    /**
     * Cập nhật địa điểm họp.
     *
     * @urlParam meetingLocation integer required ID địa điểm họp. Example: 1
     * @bodyParam name string Tên địa điểm họp. Example: Phòng họp A1
     * @bodyParam description string Mô tả địa điểm họp. Example: Phòng họp trực tuyến kết hợp
     * @bodyParam status string Trạng thái danh mục. Example: inactive
     */
    public function update(UpdateCatalogRequest $request, MeetingLocation $meetingLocation)
    {
        $item = $this->catalogService->update($meetingLocation, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Cập nhật địa điểm cuộc họp thành công!');
    }

    /**
     * Xóa địa điểm họp.
     *
     * @urlParam meetingLocation integer required ID địa điểm họp. Example: 1
     */
    public function destroy(MeetingLocation $meetingLocation)
    {
        $this->catalogService->destroy($meetingLocation);

        return $this->success(null, 'Xóa địa điểm cuộc họp thành công!');
    }

    /**
     * Xóa hàng loạt địa điểm họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy(MeetingLocation::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt địa điểm họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cần cập nhật trạng thái. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus(MeetingLocation::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái địa điểm họp.
     *
     * @urlParam meetingLocation integer required ID địa điểm họp. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingLocation $meetingLocation)
    {
        $item = $this->catalogService->changeStatus($meetingLocation, $request->status);

        return $this->successResource(new CatalogResource($item), 'Đổi trạng thái thành công!');
    }
}
