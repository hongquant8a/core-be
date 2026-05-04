<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingConclusion;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\StoreMeetingConclusionRequest;
use App\Modules\Meeting\Requests\UpdateMeetingConclusionRequest;
use App\Modules\Meeting\Resources\MeetingConclusionResource;
use App\Modules\Meeting\Services\MeetingConclusionService;

/**
 * @group Meeting - Kết luận cuộc họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý kết luận cuộc họp và tệp đính kèm kết luận.
 */
class MeetingConclusionController extends Controller
{
    public function __construct(private MeetingConclusionService $meetingConclusionService) {}

    /**
     * Thống kê kết luận cuộc họp.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái kết luận. Example: published
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingConclusionService->stats($request->all()));
    }

    /**
     * Danh sách kết luận cuộc họp.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái kết luận. Example: draft
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingConclusionService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingConclusionResource::collection($items));
    }

    /**
     * Chi tiết kết luận cuộc họp.
     *
     * @urlParam meetingConclusion integer required ID kết luận. Example: 1
     */
    public function show(MeetingConclusion $meetingConclusion)
    {
        return $this->successResource(new MeetingConclusionResource($this->meetingConclusionService->show($meetingConclusion)));
    }

    /**
     * Tạo kết luận cuộc họp.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam title string required Tiêu đề kết luận. Example: Kết luận phiên họp sáng
     * @bodyParam content string required Nội dung kết luận. Example: Thống nhất 5 nhóm nhiệm vụ trọng tâm
     * @bodyParam file file Tệp kết luận đính kèm. Example: (binary)
     * @bodyParam status string Trạng thái kết luận. Example: draft
     */
    public function store(StoreMeetingConclusionRequest $request)
    {
        $item = $this->meetingConclusionService->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingConclusionResource($item), 'Tạo kết luận họp thành công!', 201);
    }

    /**
     * Cập nhật kết luận cuộc họp.
     *
     * @urlParam meetingConclusion integer required ID kết luận. Example: 1
     * @bodyParam title string Tiêu đề kết luận. Example: Kết luận phiên họp chiều
     * @bodyParam content string Nội dung kết luận. Example: Bổ sung thêm nhiệm vụ giám sát
     * @bodyParam file file Tệp kết luận mới (nếu thay thế). Example: (binary)
     * @bodyParam status string Trạng thái kết luận. Example: published
     */
    public function update(UpdateMeetingConclusionRequest $request, MeetingConclusion $meetingConclusion)
    {
        $item = $this->meetingConclusionService->update($meetingConclusion, $request->validated(), $request->file('file'));

        return $this->successResource(new MeetingConclusionResource($item), 'Cập nhật kết luận họp thành công!');
    }

    /**
     * Xóa kết luận cuộc họp.
     *
     * @urlParam meetingConclusion integer required ID kết luận. Example: 1
     */
    public function destroy(MeetingConclusion $meetingConclusion)
    {
        $this->meetingConclusionService->destroy($meetingConclusion);

        return $this->success(null, 'Xóa kết luận họp thành công!');
    }

    /**
     * Xóa hàng loạt kết luận cuộc họp.
     *
     * @bodyParam ids integer[] required Danh sách ID kết luận cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingConclusionService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }
}
