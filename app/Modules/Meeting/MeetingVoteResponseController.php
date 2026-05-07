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
 *
 * Ownership theo spec section 7.4:
 *   - store: đại biểu tự bỏ phiếu (auto-derive participant từ auth user qua topic.meeting_id).
 *     Spec line 165: chỉ vote khi topic.status='opened'.
 *   - update: chỉ owner đại biểu sửa phiếu của mình + topic chưa closed.
 *   - destroy / bulkDestroy: defer admin (Sprint 1 sẽ enforce qua middleware meeting.role).
 *   - stats: aggregate, ai cũng xem được.
 *   - index: anonymous logic FE handle theo topic.ballot_mode.
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
     * Đại biểu bỏ phiếu — auto-derive participant từ auth user.
     *
     * BE tự tìm participant của user trong meeting của topic. User không phải đại biểu của
     * meeting → 404. FE chỉ gửi `meeting_vote_topic_id + option`. Topic phải `opened`.
     * Idempotent: vote lần 2 sẽ update phiếu cũ (cùng option).
     *
     * @bodyParam meeting_vote_topic_id integer required ID chương trình biểu quyết. Example: 1
     * @bodyParam option string required Lựa chọn biểu quyết (agree | disagree | approve | reject | abstain). Example: agree
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
     * Cập nhật phiếu biểu quyết — owner đại biểu only.
     *
     * Topic.status='closed' → ValidationException (không sửa được sau khi đóng).
     * User khác cố sửa phiếu → 404.
     *
     * @urlParam meetingVoteResponse integer required ID phiếu biểu quyết. Example: 1
     * @bodyParam option string required Lựa chọn biểu quyết mới. Example: abstain
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
