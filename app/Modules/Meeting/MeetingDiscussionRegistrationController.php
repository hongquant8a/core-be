<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\ReorderMeetingDiscussionRegistrationRequest;
use App\Modules\Meeting\Requests\StoreMeetingDiscussionRegistrationRequest;
use App\Modules\Meeting\Requests\UpdateMeetingDiscussionRegistrationRequest;
use App\Modules\Meeting\Resources\MeetingDiscussionRegistrationResource;
use App\Modules\Meeting\Services\MeetingDiscussionRegistrationService;

/**
 * @group Meeting - Thảo luận và chất vấn
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý đăng ký thảo luận/chất vấn trong cuộc họp.
 */
class MeetingDiscussionRegistrationController extends Controller
{
    public function __construct(private MeetingDiscussionRegistrationService $meetingDiscussionRegistrationService) {}

    /**
     * Thống kê đăng ký thảo luận/chất vấn.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái đăng ký. Example: approved
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingDiscussionRegistrationService->stats($request->all()));
    }

    /**
     * Danh sách đăng ký thảo luận/chất vấn.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_participant_id integer Lọc theo người tham dự. Example: 1
     * @queryParam discussion_type string Lọc theo loại đăng ký. Example: discussion
     * @queryParam status string Lọc theo trạng thái đăng ký. Example: pending
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingDiscussionRegistrationService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingDiscussionRegistrationResource::collection($items));
    }

    /**
     * Chi tiết đăng ký thảo luận/chất vấn.
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function show(MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        return $this->successResource(new MeetingDiscussionRegistrationResource($this->meetingDiscussionRegistrationService->show($meetingDiscussionRegistration)));
    }

    /**
     * Tạo đăng ký thảo luận/chất vấn.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_participant_id integer required ID người tham dự. Example: 1
     * @bodyParam discussion_type string required Loại đăng ký. Example: discussion
     * @bodyParam topic string required Chủ đề đăng ký. Example: Giải pháp chuyển đổi số
     * @bodyParam content string Nội dung đăng ký. Example: Đề xuất nhóm giải pháp trọng tâm
     * @bodyParam file file Tệp tài liệu kèm theo. Example: (binary)
     * @bodyParam status string Trạng thái đăng ký. Example: pending
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 1
     */
    public function store(StoreMeetingDiscussionRegistrationRequest $request)
    {
        $item = $this->meetingDiscussionRegistrationService->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingDiscussionRegistrationResource($item), 'Đăng ký thảo luận/chất vấn thành công!', 201);
    }

    /**
     * Cập nhật đăng ký thảo luận/chất vấn.
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     * @bodyParam discussion_type string Loại đăng ký. Example: question
     * @bodyParam topic string Chủ đề đăng ký. Example: Chất vấn tiến độ dự án
     * @bodyParam content string Nội dung đăng ký. Example: Làm rõ nguyên nhân chậm tiến độ
     * @bodyParam file file Tệp tài liệu kèm theo. Example: (binary)
     * @bodyParam status string Trạng thái đăng ký. Example: approved
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 2
     */
    public function update(UpdateMeetingDiscussionRegistrationRequest $request, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        $item = $this->meetingDiscussionRegistrationService->update($meetingDiscussionRegistration, $request->validated(), $request->file('file'));

        return $this->successResource(new MeetingDiscussionRegistrationResource($item), 'Cập nhật đăng ký thảo luận/chất vấn thành công!');
    }

    /**
     * Xóa đăng ký thảo luận/chất vấn.
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function destroy(MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        $this->meetingDiscussionRegistrationService->destroy($meetingDiscussionRegistration);

        return $this->success(null, 'Xóa đăng ký thảo luận/chất vấn thành công!');
    }

    /**
     * Xóa hàng loạt đăng ký thảo luận/chất vấn.
     *
     * @bodyParam ids integer[] required Danh sách ID đăng ký cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingDiscussionRegistrationService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Sắp xếp thứ tự đăng ký thảo luận/chất vấn.
     *
     * @bodyParam items object[] required Danh sách đăng ký cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingDiscussionRegistrationRequest $request)
    {
        $this->meetingDiscussionRegistrationService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp danh sách đăng ký thành công!');
    }
}
