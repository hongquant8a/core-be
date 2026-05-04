<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\ReorderMeetingVoteTopicRequest;
use App\Modules\Meeting\Requests\StoreMeetingVoteTopicRequest;
use App\Modules\Meeting\Requests\UpdateMeetingVoteTopicRequest;
use App\Modules\Meeting\Resources\MeetingVoteTopicCollection;
use App\Modules\Meeting\Resources\MeetingVoteTopicResource;
use App\Modules\Meeting\Services\MeetingVoteTopicService;

/**
 * @group Meeting - Chương trình biểu quyết
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý chương trình biểu quyết, mở/đóng biểu quyết và sắp xếp thứ tự.
 */
class MeetingVoteTopicController extends Controller
{
    public function __construct(private MeetingVoteTopicService $meetingVoteTopicService) {}

    /**
     * Thống kê chương trình biểu quyết.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái chương trình biểu quyết. Example: opening
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingVoteTopicService->stats($request->all()));
    }

    /**
     * Danh sách chương trình biểu quyết.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam vote_type string Lọc theo loại biểu quyết. Example: single_choice
     * @queryParam status string Lọc theo trạng thái chương trình biểu quyết. Example: draft
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingVoteTopicService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingVoteTopicCollection($items));
    }

    /**
     * Chi tiết chương trình biểu quyết.
     *
     * @urlParam meetingVoteTopic integer required ID chương trình biểu quyết. Example: 1
     */
    public function show(MeetingVoteTopic $meetingVoteTopic)
    {
        return $this->successResource(new MeetingVoteTopicResource($this->meetingVoteTopicService->show($meetingVoteTopic)));
    }

    /**
     * Tạo chương trình biểu quyết.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam title string required Tiêu đề chương trình biểu quyết. Example: Biểu quyết thông qua nghị quyết
     * @bodyParam description string Nội dung chương trình biểu quyết. Example: Đại biểu chọn đồng ý/không đồng ý
     * @bodyParam vote_type string required Loại biểu quyết. Example: single_choice
     * @bodyParam ballot_mode string required Hình thức bỏ phiếu. Example: public
     * @bodyParam options string[] required Danh sách phương án biểu quyết. Example: ["approve","reject","abstain"]
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 1
     */
    public function store(StoreMeetingVoteTopicRequest $request)
    {
        $item = $this->meetingVoteTopicService->store($request->validated());

        return $this->successResource(new MeetingVoteTopicResource($item), 'Tạo chương trình biểu quyết thành công!', 201);
    }

    /**
     * Cập nhật chương trình biểu quyết.
     *
     * @urlParam meetingVoteTopic integer required ID chương trình biểu quyết. Example: 1
     * @bodyParam title string Tiêu đề chương trình biểu quyết. Example: Biểu quyết điều chỉnh nghị quyết
     * @bodyParam description string Nội dung chương trình biểu quyết. Example: Cập nhật các chỉ tiêu chính
     * @bodyParam vote_type string Loại biểu quyết. Example: single_choice
     * @bodyParam ballot_mode string Hình thức bỏ phiếu. Example: secret
     * @bodyParam options string[] Danh sách phương án biểu quyết. Example: ["approve","reject","abstain"]
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 2
     */
    public function update(UpdateMeetingVoteTopicRequest $request, MeetingVoteTopic $meetingVoteTopic)
    {
        $item = $this->meetingVoteTopicService->update($meetingVoteTopic, $request->validated());

        return $this->successResource(new MeetingVoteTopicResource($item), 'Cập nhật chương trình biểu quyết thành công!');
    }

    /**
     * Xóa chương trình biểu quyết.
     *
     * @urlParam meetingVoteTopic integer required ID chương trình biểu quyết. Example: 1
     */
    public function destroy(MeetingVoteTopic $meetingVoteTopic)
    {
        $this->meetingVoteTopicService->destroy($meetingVoteTopic);

        return $this->success(null, 'Xóa chương trình biểu quyết thành công!');
    }

    /**
     * Xóa hàng loạt chương trình biểu quyết.
     *
     * @bodyParam ids integer[] required Danh sách ID chương trình biểu quyết cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingVoteTopicService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Mở biểu quyết.
     *
     * @urlParam meetingVoteTopic integer required ID chương trình biểu quyết. Example: 1
     */
    public function open(MeetingVoteTopic $meetingVoteTopic)
    {
        $item = $this->meetingVoteTopicService->open($meetingVoteTopic);

        return $this->successResource(new MeetingVoteTopicResource($item), 'Mở biểu quyết thành công!');
    }

    /**
     * Đóng biểu quyết.
     *
     * @urlParam meetingVoteTopic integer required ID chương trình biểu quyết. Example: 1
     */
    public function close(MeetingVoteTopic $meetingVoteTopic)
    {
        $item = $this->meetingVoteTopicService->close($meetingVoteTopic);

        return $this->successResource(new MeetingVoteTopicResource($item), 'Đóng biểu quyết thành công!');
    }

    /**
     * Sắp xếp thứ tự chương trình biểu quyết.
     *
     * @bodyParam items object[] required Danh sách chương trình biểu quyết cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingVoteTopicRequest $request)
    {
        $this->meetingVoteTopicService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp chương trình biểu quyết thành công!');
    }
}
