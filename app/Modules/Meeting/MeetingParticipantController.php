<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\StoreMeetingParticipantRequest;
use App\Modules\Meeting\Requests\UpdateMeetingParticipantRequest;
use App\Modules\Meeting\Resources\MeetingParticipantCollection;
use App\Modules\Meeting\Resources\MeetingParticipantResource;
use App\Modules\Meeting\Services\MeetingParticipantService;

/**
 * @group Meeting - Người tham dự
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý người tham dự của từng cuộc họp (snapshot từ danh bạ đại biểu).
 */
class MeetingParticipantController extends Controller
{
    public function __construct(private MeetingParticipantService $meetingParticipantService) {}

    /**
     * Thống kê người tham dự.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam response_status string Lọc theo trạng thái phản hồi tham dự. Example: accepted
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingParticipantService->stats($request->all()));
    }

    /**
     * Danh sách người tham dự.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_attendee_id integer Lọc theo danh bạ đại biểu. Example: 1
     * @queryParam role string Lọc theo vai trò tham dự. Example: delegate
     * @queryParam response_status string Lọc theo trạng thái phản hồi tham dự. Example: pending
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingParticipantService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingParticipantCollection($items));
    }

    /**
     * Chi tiết người tham dự.
     *
     * @urlParam meetingParticipant integer required ID người tham dự. Example: 1
     */
    public function show(MeetingParticipant $meetingParticipant)
    {
        return $this->successResource(new MeetingParticipantResource($this->meetingParticipantService->show($meetingParticipant)));
    }

    /**
     * Thêm người tham dự vào cuộc họp.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_attendee_id integer required ID đại biểu từ danh bạ. Example: 1
     * @bodyParam role string required Vai trò tham dự. Example: delegate
     * @bodyParam response_status string Trạng thái phản hồi tham dự. Example: pending
     * @bodyParam note string Ghi chú tham dự. Example: Đại biểu tham dự trực tuyến
     */
    public function store(StoreMeetingParticipantRequest $request)
    {
        $item = $this->meetingParticipantService->store($request->validated());

        return $this->successResource(new MeetingParticipantResource($item), 'Thêm đại biểu tham dự thành công!', 201);
    }

    /**
     * Cập nhật người tham dự.
     *
     * @urlParam meetingParticipant integer required ID người tham dự. Example: 1
     * @bodyParam role string Vai trò tham dự. Example: chairperson
     * @bodyParam response_status string Trạng thái phản hồi tham dự. Example: accepted
     * @bodyParam note string Ghi chú tham dự. Example: Xác nhận có mặt đầy đủ
     */
    public function update(UpdateMeetingParticipantRequest $request, MeetingParticipant $meetingParticipant)
    {
        $item = $this->meetingParticipantService->update($meetingParticipant, $request->validated());

        return $this->successResource(new MeetingParticipantResource($item), 'Cập nhật người tham dự thành công!');
    }

    /**
     * Xóa người tham dự.
     *
     * @urlParam meetingParticipant integer required ID người tham dự. Example: 1
     */
    public function destroy(MeetingParticipant $meetingParticipant)
    {
        $this->meetingParticipantService->destroy($meetingParticipant);

        return $this->success(null, 'Xóa người tham dự thành công!');
    }

    /**
     * Xóa hàng loạt người tham dự.
     *
     * @bodyParam ids integer[] required Danh sách ID người tham dự cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingParticipantService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }
}
