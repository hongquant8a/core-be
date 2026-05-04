<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\StoreMeetingAttendanceRequest;
use App\Modules\Meeting\Requests\UpdateMeetingAttendanceRequest;
use App\Modules\Meeting\Resources\MeetingAttendanceCollection;
use App\Modules\Meeting\Resources\MeetingAttendanceResource;
use App\Modules\Meeting\Services\MeetingAttendanceService;

/**
 * @group Meeting - Điểm danh
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý điểm danh đại biểu trong cuộc họp.
 */
class MeetingAttendanceController extends Controller
{
    public function __construct(private MeetingAttendanceService $meetingAttendanceService) {}

    /**
     * Thống kê điểm danh theo cuộc họp.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái điểm danh. Example: present
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->meetingAttendanceService->stats($request->all()));
    }

    /**
     * Danh sách điểm danh.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_participant_id integer Lọc theo người tham dự. Example: 1
     * @queryParam status string Lọc theo trạng thái điểm danh. Example: absent
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingAttendanceService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingAttendanceCollection($items));
    }

    /**
     * Chi tiết điểm danh.
     *
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function show(MeetingAttendance $meetingAttendance)
    {
        return $this->successResource(new MeetingAttendanceResource($this->meetingAttendanceService->show($meetingAttendance)));
    }

    /**
     * Tạo bản ghi điểm danh.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_participant_id integer required ID người tham dự. Example: 1
     * @bodyParam status string required Trạng thái điểm danh. Example: present
     * @bodyParam checkin_at datetime Thời gian điểm danh (Y-m-d H:i:s). Example: 2026-05-01 07:55:00
     * @bodyParam checkin_method string Phương thức điểm danh. Example: manual
     * @bodyParam note string Ghi chú điểm danh. Example: Đến đúng giờ
     */
    public function store(StoreMeetingAttendanceRequest $request)
    {
        $item = $this->meetingAttendanceService->store($request->validated());

        return $this->successResource(new MeetingAttendanceResource($item), 'Điểm danh thành công!', 201);
    }

    /**
     * Cập nhật bản ghi điểm danh.
     *
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     * @bodyParam status string Trạng thái điểm danh. Example: late
     * @bodyParam checkin_at datetime Thời gian điểm danh (Y-m-d H:i:s). Example: 2026-05-01 08:10:00
     * @bodyParam checkin_method string Phương thức điểm danh. Example: qr
     * @bodyParam note string Ghi chú điểm danh. Example: Đến muộn do kẹt xe
     */
    public function update(UpdateMeetingAttendanceRequest $request, MeetingAttendance $meetingAttendance)
    {
        $item = $this->meetingAttendanceService->update($meetingAttendance, $request->validated());

        return $this->successResource(new MeetingAttendanceResource($item), 'Cập nhật điểm danh thành công!');
    }

    /**
     * Xóa bản ghi điểm danh.
     *
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function destroy(MeetingAttendance $meetingAttendance)
    {
        $this->meetingAttendanceService->destroy($meetingAttendance);

        return $this->success(null, 'Xóa điểm danh thành công!');
    }

    /**
     * Xóa hàng loạt bản ghi điểm danh.
     *
     * @bodyParam ids integer[] required Danh sách ID điểm danh cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingAttendanceService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }
}
