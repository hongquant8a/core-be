<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\StoreMeetingVoteResponseRequest;
use App\Modules\Meeting\Requests\UpdateMeetingVoteResponseRequest;
use App\Modules\Meeting\Resources\MeetingVoteResponseResource;
use App\Modules\Meeting\Services\MeetingVoteResponseService;

/**
 * @group Meeting - Phiếu biểu quyết
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý phiếu biểu quyết của đại biểu theo từng chương trình biểu quyết.
 */
class MeetingVoteResponseController extends Controller
{
    public function __construct(private MeetingVoteResponseService $meetingVoteResponseService) {}

    /**
     * Thống kê phiếu biểu quyết.
     *
     * @queryParam meeting_vote_topic_id integer Lọc theo chương trình biểu quyết. Example: 1
     * @queryParam meeting_participant_id integer Lọc theo người tham dự. Example: 1
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingVoteResponseService->stats($request->all()));
    }

    /**
     * Danh sách phiếu biểu quyết.
     *
     * @queryParam meeting_vote_topic_id integer Lọc theo chương trình biểu quyết. Example: 1
     * @queryParam meeting_participant_id integer Lọc theo người tham dự. Example: 1
     * @queryParam selected_option string Lọc theo lựa chọn biểu quyết. Example: approve
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingVoteResponseService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingVoteResponseResource::collection($items));
    }

    /**
     * Tạo hoặc cập nhật phiếu biểu quyết của đại biểu.
     *
     * @bodyParam meeting_vote_topic_id integer required ID chương trình biểu quyết. Example: 1
     * @bodyParam meeting_participant_id integer required ID người tham dự. Example: 1
     * @bodyParam selected_option string required Lựa chọn biểu quyết. Example: approve
     * @bodyParam note string Ghi chú biểu quyết. Example: Đồng thuận theo tổ
     */
    public function store(StoreMeetingVoteResponseRequest $request)
    {
        $item = $this->meetingVoteResponseService->store($request->validated());

        return $this->successResource(new MeetingVoteResponseResource($item), 'Ghi nhận phiếu biểu quyết thành công!', 201);
    }

    /**
     * Chi tiết phiếu biểu quyết.
     *
     * @urlParam meetingVoteResponse integer required ID phiếu biểu quyết. Example: 1
     */
    public function show(MeetingVoteResponse $meetingVoteResponse)
    {
        return $this->successResource(new MeetingVoteResponseResource($this->meetingVoteResponseService->show($meetingVoteResponse)));
    }

    /**
     * Cập nhật phiếu biểu quyết.
     *
     * @urlParam meetingVoteResponse integer required ID phiếu biểu quyết. Example: 1
     * @bodyParam selected_option string required Lựa chọn biểu quyết. Example: reject
     * @bodyParam note string Ghi chú biểu quyết. Example: Đề nghị xem xét lại chỉ tiêu
     */
    public function update(UpdateMeetingVoteResponseRequest $request, MeetingVoteResponse $meetingVoteResponse)
    {
        $item = $this->meetingVoteResponseService->update($meetingVoteResponse, $request->validated());

        return $this->successResource(new MeetingVoteResponseResource($item), 'Cập nhật phiếu biểu quyết thành công!');
    }

    /**
     * Xóa phiếu biểu quyết.
     *
     * @urlParam meetingVoteResponse integer required ID phiếu biểu quyết. Example: 1
     */
    public function destroy(MeetingVoteResponse $meetingVoteResponse)
    {
        $this->meetingVoteResponseService->destroy($meetingVoteResponse);

        return $this->success(null, 'Xóa phiếu biểu quyết thành công!');
    }

    /**
     * Xóa hàng loạt phiếu biểu quyết.
     *
     * @bodyParam ids integer[] required Danh sách ID phiếu biểu quyết cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingVoteResponseService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }
}
