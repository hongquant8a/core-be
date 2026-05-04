<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;

/**
 * @group Meeting - Nhóm đại biểu
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý nhóm đại biểu phục vụ mời họp.
 */
class MeetingAttendeeGroupController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    /**
     * Thống kê nhóm đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên nhóm. Example: tổ đại biểu 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats(MeetingAttendeeGroup::class, $request->all()));
    }

    /**
     * Danh sách nhóm đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên nhóm. Example: tổ đại biểu 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index(MeetingAttendeeGroup::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    /**
     * Chi tiết nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     */
    public function show(MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($meetingAttendeeGroup)));
    }

    /**
     * Tạo nhóm đại biểu.
     *
     * @bodyParam name string required Tên nhóm đại biểu. Example: Tổ đại biểu số 1
     * @bodyParam description string Mô tả nhóm đại biểu. Example: Nhóm đại biểu khu vực trung tâm
     * @bodyParam status string Trạng thái nhóm. Example: active
     */
    public function store(StoreCatalogRequest $request)
    {
        $item = $this->catalogService->store(MeetingAttendeeGroup::class, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Tạo nhóm đại biểu thành công!', 201);
    }

    /**
     * Cập nhật nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     * @bodyParam name string Tên nhóm đại biểu. Example: Tổ đại biểu số 2
     * @bodyParam description string Mô tả nhóm đại biểu. Example: Nhóm đại biểu khu vực ngoại thành
     * @bodyParam status string Trạng thái nhóm. Example: inactive
     */
    public function update(UpdateCatalogRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $item = $this->catalogService->update($meetingAttendeeGroup, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Cập nhật nhóm đại biểu thành công!');
    }

    /**
     * Xóa nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     */
    public function destroy(MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $this->catalogService->destroy($meetingAttendeeGroup);

        return $this->success(null, 'Xóa nhóm đại biểu thành công!');
    }

    /**
     * Xóa hàng loạt nhóm đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy(MeetingAttendeeGroup::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhóm đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID cần cập nhật trạng thái. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus(MeetingAttendeeGroup::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $item = $this->catalogService->changeStatus($meetingAttendeeGroup, $request->status);

        return $this->successResource(new CatalogResource($item), 'Đổi trạng thái thành công!');
    }
}
