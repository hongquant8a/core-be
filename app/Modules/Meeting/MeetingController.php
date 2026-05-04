<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Requests\BulkDestroyMeetingRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusMeetingRequest;
use App\Modules\Meeting\Requests\ChangeStatusMeetingRequest;
use App\Modules\Meeting\Requests\StoreMeetingRequest;
use App\Modules\Meeting\Requests\UpdateMeetingRequest;
use App\Modules\Meeting\Resources\MeetingCollection;
use App\Modules\Meeting\Resources\MeetingResource;
use App\Modules\Meeting\Services\MeetingService;

/**
 * @group Meeting - Cuộc họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý cuộc họp: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa và thao tác trạng thái.
 */
class MeetingController extends Controller
{
    public function __construct(private MeetingService $meetingService) {}

    /**
     * Danh sách cuộc họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: họp giao ban
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái cuộc họp. Example: published
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     * @queryParam sort_by string Sắp xếp theo trường. Example: start_time
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function public(FilterRequest $request)
    {
        $meetings = $this->meetingService->publicIndex($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingCollection($meetings));
    }

    /**
     * Chi tiết cuộc họp công khai.
     *
     * @unauthenticated
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function publicShow(Meeting $meeting)
    {
        return $this->successResource(new MeetingResource($this->meetingService->publicShow($meeting)));
    }

    /**
     * Thống kê cuộc họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: họp giao ban
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái cuộc họp. Example: published
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingService->stats($request->all()));
    }

    /**
     * Danh sách cuộc họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: họp giao ban
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái cuộc họp. Example: draft
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $meetings = $this->meetingService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingCollection($meetings));
    }

    /**
     * Chi tiết cuộc họp.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function show(Meeting $meeting)
    {
        return $this->successResource(new MeetingResource($this->meetingService->show($meeting)));
    }

    /**
     * Tạo cuộc họp mới.
     *
     * @bodyParam title string required Tiêu đề cuộc họp. Example: Kỳ họp HĐND thường kỳ tháng 5
     * @bodyParam meeting_type_id integer ID loại cuộc họp. Example: 1
     * @bodyParam meeting_location_id integer ID địa điểm họp. Example: 1
     * @bodyParam start_time datetime required Thời gian bắt đầu (Y-m-d H:i:s). Example: 2026-05-01 08:00:00
     * @bodyParam end_time datetime Thời gian kết thúc (Y-m-d H:i:s). Example: 2026-05-01 11:30:00
     * @bodyParam content string Nội dung cuộc họp. Example: Nội dung chi tiết phiên họp
     * @bodyParam is_public boolean Công khai cuộc họp hay không. Example: true
     * @bodyParam status string Trạng thái cuộc họp. Example: draft
     */
    public function store(StoreMeetingRequest $request)
    {
        $meeting = $this->meetingService->store($request->validated());

        return $this->successResource(new MeetingResource($meeting), 'Tạo cuộc họp thành công!', 201);
    }

    /**
     * Cập nhật cuộc họp.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam title string Tiêu đề cuộc họp. Example: Kỳ họp HĐND bổ sung tháng 5
     * @bodyParam meeting_type_id integer ID loại cuộc họp. Example: 1
     * @bodyParam meeting_location_id integer ID địa điểm họp. Example: 1
     * @bodyParam start_time datetime Thời gian bắt đầu (Y-m-d H:i:s). Example: 2026-05-01 08:30:00
     * @bodyParam end_time datetime Thời gian kết thúc (Y-m-d H:i:s). Example: 2026-05-01 11:45:00
     * @bodyParam content string Nội dung cuộc họp. Example: Nội dung đã cập nhật
     * @bodyParam is_public boolean Công khai cuộc họp hay không. Example: false
     * @bodyParam status string Trạng thái cuộc họp. Example: published
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting)
    {
        $meeting = $this->meetingService->update($meeting, $request->validated());

        return $this->successResource(new MeetingResource($meeting), 'Cập nhật cuộc họp thành công!');
    }

    /**
     * Xóa cuộc họp.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function destroy(Meeting $meeting)
    {
        $this->meetingService->destroy($meeting);

        return $this->success(null, 'Xóa cuộc họp thành công!');
    }

    /**
     * Xóa hàng loạt cuộc họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cuộc họp cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyMeetingRequest $request)
    {
        $this->meetingService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt cuộc họp thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt cuộc họp.
     *
     * @bodyParam ids integer[] required Danh sách ID cuộc họp cần cập nhật. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: cancelled
     */
    public function bulkUpdateStatus(BulkUpdateStatusMeetingRequest $request)
    {
        $this->meetingService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái cuộc họp.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam status string required Trạng thái mới của cuộc họp. Example: published
     */
    public function changeStatus(ChangeStatusMeetingRequest $request, Meeting $meeting)
    {
        $meeting = $this->meetingService->changeStatus($meeting, $request->status);

        return $this->successResource(new MeetingResource($meeting), 'Đổi trạng thái cuộc họp thành công!');
    }
}
