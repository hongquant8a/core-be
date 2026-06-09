<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Requests\BulkDestroyMeetingRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusMeetingRequest;
use App\Modules\Meeting\Requests\ChangeStatusMeetingRequest;
use App\Modules\Meeting\Requests\HighlightAgendaMeetingRequest;
use App\Modules\Meeting\Requests\HighlightDiscussionMeetingRequest;
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
     * Danh sách cuộc họp công khai kèm cây tài liệu (Document Tree).
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: họp giao ban
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái cuộc họp. Example: published
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     * @queryParam sort_by string Sắp xếp theo trường. Example: start_time
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 100
     */
    public function publicDocumentTree(FilterRequest $request)
    {
        $meetings = $this->meetingService->publicIndex($request->all(), (int) ($request->limit ?? 100));

        return $this->successCollection(\App\Modules\Meeting\Resources\MeetingDocumentTreeResource::collection($meetings));
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
     * Thống kê cuộc họp công khai (citizen-facing).
     *
     * Phase derived từ start_time/end_time vs now: total / upcoming / in_progress / finished.
     * Scope: is_public=true + status=published.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: họp giao ban
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     */
    public function publicStats(FilterRequest $request)
    {
        return $this->success($this->meetingService->publicStats($request->all()));
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
     * @bodyParam reminders object[] Danh sách mốc nhắc lịch (per-record). Gửi mảng rỗng [] nếu không có.
     * @bodyParam reminders.*.moment string required Thời điểm nhắc: before, on, after. Example: before
     * @bodyParam reminders.*.offset_minutes integer Phút offset từ start_time. Example: 30
     * @bodyParam reminders.*.channels string[] Kênh gửi: mail, sms, zalo, zalo_zns, fcm. Example: ["mail","zalo"]
     */
    public function store(StoreMeetingRequest $request)
    {
        $meeting = $this->meetingService->store(
            $request->validated(),
            $request->file('projector_image'),
            $request->input('guests', []),
            $request->input('reminders', []),
        );

        return $this->successResource(new MeetingResource($meeting), 'Tạo cuộc họp thành công!', 201);
    }

    /**
     * Sao chép cuộc họp.
     *
     * Tạo bản sao với tiêu đề có đuôi "(sao chép)". Sao chép tất cả trừ tài liệu và
     * chương trình biểu quyết. Meeting mới ở trạng thái draft.
     *
     * @urlParam meeting integer required ID cuộc họp cần sao chép. Example: 1
     */
    public function duplicate(Meeting $meeting)
    {
        $copy = $this->meetingService->duplicate($meeting);

        return $this->successResource(new MeetingResource($copy), 'Sao chép cuộc họp thành công!', 201);
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
     * @bodyParam reminders object[] Danh sách mốc nhắc lịch (per-record). Không gửi key này = giữ nguyên; gửi mảng rỗng [] = xóa hết CUSTOM.
     * @bodyParam reminders.*.moment string required Thời điểm nhắc: before, on, after. Example: before
     * @bodyParam reminders.*.offset_minutes integer Phút offset từ start_time. Example: 30
     * @bodyParam reminders.*.channels string[] Kênh gửi: mail, sms, zalo, zalo_zns, fcm. Example: ["mail","zalo"]
     */
    public function update(UpdateMeetingRequest $request, Meeting $meeting)
    {
        $meeting = $this->meetingService->update(
            $meeting,
            $request->validated(),
            $request->file('projector_image'),
            $request->has('guests') ? $request->input('guests', []) : null,
            $request->has('reminders') ? $request->input('reminders', []) : null,
        );

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

    /**
     * Kết thúc cuộc họp — operator bấm khi muốn kết thúc phiên họp.
     * BE set `status = completed`. Các hành động như điểm danh, biểu quyết sẽ bị khóa.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function endEarly(Meeting $meeting)
    {
        $item = $this->meetingService->endEarly($meeting);

        return $this->successResource(new MeetingResource($item), 'Đã kết thúc cuộc họp.');
    }

    /**
     * Mở lại cuộc họp — Đổi trạng thái từ completed về published.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function reopen(Meeting $meeting)
    {
        $item = $this->meetingService->reopen($meeting);

        return $this->successResource(new MeetingResource($item), 'Mở lại cuộc họp thành công!');
    }

    /**
     * Khoá danh sách điểm danh — đại biểu không thể checkin/báo vắng nữa.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function lockAttendance(Meeting $meeting)
    {
        $item = $this->meetingService->lockAttendance($meeting);

        return $this->successResource(new MeetingResource($item), 'Đã khoá danh sách điểm danh.');
    }

    /**
     * Lấy QR token điểm danh của cuộc họp — chỉ privileged role (chair/op/secretary).
     *
     * FE dùng token để gen QR encode URL như `${origin}/checkin/${token}`. Đại biểu
     * scan → mở URL FE → FE call POST /api/meeting-attendances/checkin-by-token.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function qrToken(Meeting $meeting)
    {
        return $this->success([
            'meeting_id' => $meeting->id,
            'token' => $meeting->checkin_token,
        ]);
    }

    /**
     * Mở khoá danh sách điểm danh.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function unlockAttendance(Meeting $meeting)
    {
        $item = $this->meetingService->unlockAttendance($meeting);

        return $this->successResource(new MeetingResource($item), 'Đã mở khoá danh sách điểm danh.');
    }

    /**
     * Highlight 1 chương trình lên màn chiếu (Tab 8). Truyền agenda_id=null để bỏ highlight.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function highlightAgenda(HighlightAgendaMeetingRequest $request, Meeting $meeting)
    {
        $item = $this->meetingService->highlightAgenda($meeting, $request->input('agenda_id'));

        return $this->successResource(new MeetingResource($item), 'Đã cập nhật chương trình đang chiếu.');
    }

    /**
     * Highlight 1 đăng ký phát biểu/chất vấn lên màn chiếu. Truyền discussion_registration_id=null để bỏ highlight.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function highlightDiscussion(HighlightDiscussionMeetingRequest $request, Meeting $meeting)
    {
        $item = $this->meetingService->highlightDiscussion($meeting, $request->input('discussion_registration_id'));

        return $this->successResource(new MeetingResource($item), 'Đã cập nhật phát biểu đang chiếu.');
    }

    /**
     * Xuất Excel cuộc họp.
     *
     * Xuất ra các trường: STT, Tiêu đề, Loại cuộc họp, Địa điểm, Công khai, Thời gian bắt đầu, Thời gian kết thúc, Trạng thái, Lượt xem, Thời gian phát hành, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề. Example: HĐND
     * @queryParam meeting_type_id integer Lọc theo loại cuộc họp. Example: 1
     * @queryParam status string Lọc theo trạng thái. Example: published
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-05-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-05-31
     */
    public function export(FilterRequest $request)
    {
        return $this->meetingService->export($request->all());
    }
}
