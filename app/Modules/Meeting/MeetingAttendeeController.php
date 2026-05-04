<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\StoreMeetingAttendeeRequest;
use App\Modules\Meeting\Requests\UpdateMeetingAttendeeRequest;
use App\Modules\Meeting\Resources\MeetingAttendeeCollection;
use App\Modules\Meeting\Resources\MeetingAttendeeResource;
use App\Modules\Meeting\Services\MeetingAttendeeService;

/**
 * @group Meeting - Đại biểu
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý danh bạ đại biểu dùng để thêm vào danh sách tham dự cuộc họp.
 */
class MeetingAttendeeController extends Controller
{
    public function __construct(private MeetingAttendeeService $meetingAttendeeService) {}

    /**
     * Thống kê đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên/email/đơn vị. Example: nguyen van a
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingAttendeeService->stats($request->all()));
    }

    /**
     * Danh sách đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên/email/đơn vị. Example: nguyen van a
     * @queryParam meeting_attendee_group_id integer Lọc theo nhóm đại biểu. Example: 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingAttendeeService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingAttendeeCollection($items));
    }

    /**
     * Chi tiết đại biểu.
     *
     * @urlParam meetingAttendee integer required ID đại biểu. Example: 1
     */
    public function show(MeetingAttendee $meetingAttendee)
    {
        return $this->successResource(new MeetingAttendeeResource($this->meetingAttendeeService->show($meetingAttendee)));
    }

    /**
     * Tạo đại biểu.
     *
     * @bodyParam name string required Họ tên đại biểu. Example: Nguyễn Văn A
     * @bodyParam email string Email đại biểu. Example: nva@example.com
     * @bodyParam phone string Số điện thoại đại biểu. Example: 0901234567
     * @bodyParam meeting_attendee_group_id integer ID nhóm đại biểu. Example: 1
     * @bodyParam organization_name string Đơn vị công tác. Example: Sở Nội vụ
     * @bodyParam position_name string Chức vụ. Example: Chuyên viên
     * @bodyParam status string Trạng thái đại biểu. Example: active
     */
    public function store(StoreMeetingAttendeeRequest $request)
    {
        $item = $this->meetingAttendeeService->store($request->validated());

        return $this->successResource(new MeetingAttendeeResource($item), 'Tạo đại biểu thành công!', 201);
    }

    /**
     * Cập nhật đại biểu.
     *
     * @urlParam meetingAttendee integer required ID đại biểu. Example: 1
     * @bodyParam name string Họ tên đại biểu. Example: Trần Thị B
     * @bodyParam email string Email đại biểu. Example: ttb@example.com
     * @bodyParam phone string Số điện thoại đại biểu. Example: 0912345678
     * @bodyParam meeting_attendee_group_id integer ID nhóm đại biểu. Example: 2
     * @bodyParam organization_name string Đơn vị công tác. Example: Văn phòng HĐND
     * @bodyParam position_name string Chức vụ. Example: Phó phòng
     * @bodyParam status string Trạng thái đại biểu. Example: inactive
     */
    public function update(UpdateMeetingAttendeeRequest $request, MeetingAttendee $meetingAttendee)
    {
        $item = $this->meetingAttendeeService->update($meetingAttendee, $request->validated());

        return $this->successResource(new MeetingAttendeeResource($item), 'Cập nhật đại biểu thành công!');
    }

    /**
     * Xóa đại biểu.
     *
     * @urlParam meetingAttendee integer required ID đại biểu. Example: 1
     */
    public function destroy(MeetingAttendee $meetingAttendee)
    {
        $this->meetingAttendeeService->destroy($meetingAttendee);

        return $this->success(null, 'Xóa đại biểu thành công!');
    }

    /**
     * Xóa hàng loạt đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID đại biểu cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingAttendeeService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID đại biểu cần cập nhật. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->meetingAttendeeService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái đại biểu.
     *
     * @urlParam meetingAttendee integer required ID đại biểu. Example: 1
     * @bodyParam status string required Trạng thái mới của đại biểu. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingAttendee $meetingAttendee)
    {
        $item = $this->meetingAttendeeService->changeStatus($meetingAttendee, $request->status);

        return $this->successResource(new MeetingAttendeeResource($item), 'Đổi trạng thái thành công!');
    }
}
