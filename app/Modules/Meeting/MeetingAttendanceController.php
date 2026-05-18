<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\CheckinMeetingAttendanceRequest;
use App\Modules\Meeting\Requests\MarkAbsentMeetingAttendanceRequest;
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

    /**
     * Operator duyệt điểm danh — pending → present.
     *
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function approve(MeetingAttendance $meetingAttendance)
    {
        $item = $this->meetingAttendanceService->approve($meetingAttendance);

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã duyệt điểm danh.');
    }

    /**
     * Operator từ chối điểm danh — pending → absent.
     *
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function reject(MeetingAttendance $meetingAttendance)
    {
        $item = $this->meetingAttendanceService->reject($meetingAttendance);

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã từ chối điểm danh.');
    }

    /**
     * Đại biểu tự điểm danh — auto-derive participant từ auth user.
     *
     * Status=`pending` chờ operator duyệt (Tab 7 sẽ có approve/reject ở Sprint 1).
     * Idempotent: F5/click lần 2 không tạo trùng — update row hiện có.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     */
    public function checkin(CheckinMeetingAttendanceRequest $request)
    {
        $item = $this->meetingAttendanceService->checkin((int) $request->validated('meeting_id'));

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận điểm danh, chờ duyệt.');
    }

    /**
     * Điểm danh qua QR token (đại biểu scan QR → FE call endpoint này).
     *
     * BE auto-resolve meeting từ token + check participant từ auth user. Token sai → 404.
     * Meeting chưa publish → 422. Logic checkin giống endpoint button thường.
     *
     * @bodyParam token string required UUID checkin của meeting. Example: 550e8400-e29b-41d4-a716-446655440000
     */
    public function checkinByToken(\App\Modules\Meeting\Requests\CheckinByTokenRequest $request)
    {
        $item = $this->meetingAttendanceService->checkinByToken($request->validated('token'));

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận điểm danh qua QR, chờ duyệt.');
    }

    /**
     * Thư ký/operator điểm danh hộ đại biểu (offline scenario: đại biểu không dùng app).
     *
     * Status có thể là `present` (xác nhận có mặt) hoặc `absent` (báo vắng hộ).
     * Permission `meeting-attendances.store` enforce role privileged (chair/op/secretary).
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_participant_id integer required ID đại biểu được điểm danh hộ. Example: 12
     * @bodyParam status string required Trạng thái: present | absent. Example: present
     * @bodyParam note string Ghi chú lý do (optional). Example: Bị ốm đột xuất
     */
    public function manualCheckin(\App\Modules\Meeting\Requests\ManualCheckinRequest $request)
    {
        $item = $this->meetingAttendanceService->manualCheckin(
            (int) $request->validated('meeting_id'),
            (int) $request->validated('meeting_participant_id'),
            $request->validated('status'),
            $request->validated('note'),
        );

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã điểm danh hộ đại biểu.');
    }

    /**
     * Đại biểu tự báo vắng — auto-derive participant từ auth user.
     *
     * Status=`absent` set ngay, không cần operator duyệt. Idempotent: nếu đã có row checkin,
     * update sang absent + ghi note.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam note string Lý do vắng (optional). Example: Bị ốm đột xuất
     */
    public function markAbsent(MarkAbsentMeetingAttendanceRequest $request)
    {
        $item = $this->meetingAttendanceService->markAbsent(
            (int) $request->validated('meeting_id'),
            $request->validated('note'),
        );

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận báo vắng.');
    }

    /**
     * Xuất danh sách đại biểu tham dự + trạng thái điểm danh ra Excel.
     *
     * Auth-only, không qua Spatie permission. Gate qua MeetingPolicy::operate
     * (chair/operator của meeting).
     *
     * Xuất ra các trường: STT, Tên đại biểu, Chức vụ, Xác nhận tham gia, Đã điểm danh,
     * Đã xác nhận điểm danh, Giờ điểm danh.
     *
     * @queryParam meeting_id integer required ID cuộc họp. Example: 1
     */
    public function export(FilterRequest $request)
    {
        $request->validate([
            'meeting_id' => 'required|integer|exists:meetings,id',
        ]);

        $meetingId = (int) $request->input('meeting_id');
        $meeting = \App\Modules\Meeting\Models\Meeting::findOrFail($meetingId);
        \Illuminate\Support\Facades\Gate::authorize('exportReports', $meeting);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingAttendanceExport($meetingId),
            ExportFilename::make('diem-danh-hop'),
        );
    }

    /**
     * Nested route `PATCH /api/meetings/{meeting}/attendances/{att}/approve` — gate approve.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function approveInMeeting(Meeting $meeting, MeetingAttendance $meetingAttendance)
    {
        return $this->approve($meetingAttendance);
    }

    /**
     * Nested route `PATCH /api/meetings/{meeting}/attendances/{att}/reject` — gate reject.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingAttendance integer required ID điểm danh. Example: 1
     */
    public function rejectInMeeting(Meeting $meeting, MeetingAttendance $meetingAttendance)
    {
        return $this->reject($meetingAttendance);
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/attendances/stats` — gate operate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function statsInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $filters = array_merge($request->all(), ['meeting_id' => $meeting->id]);

        return $this->success($this->meetingAttendanceService->stats($filters));
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/attendances` — gate operate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function indexInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $filters = array_merge($request->all(), ['meeting_id' => $meeting->id]);
        $items = $this->meetingAttendanceService->index($filters, (int) ($request->limit ?? 50));

        return $this->successCollection(new MeetingAttendanceCollection($items));
    }

    /**
     * Nested route `POST /api/meetings/{meeting}/attendances/checkin` — gate participate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function checkinInMeeting(Meeting $meeting, \App\Modules\Meeting\Requests\CheckinMeetingAttendanceRequest $request)
    {
        $item = $this->meetingAttendanceService->checkin($meeting->id);

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận điểm danh, chờ duyệt.');
    }

    /**
     * Nested route `POST /api/meetings/{meeting}/attendances/checkin-by-token` — gate participate.
     *
     * Token vẫn được dùng để BE map về meeting cụ thể (token là UUID của meeting nào).
     * Mismatch token vs URL meeting → 422.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @bodyParam token string required UUID checkin của meeting. Example: 550e8400-e29b-41d4-a716-446655440000
     */
    public function checkinByTokenInMeeting(Meeting $meeting, \App\Modules\Meeting\Requests\CheckinByTokenRequest $request)
    {
        $token = $request->validated('token');
        if ($token !== $meeting->checkin_token) {
            return $this->error('Token điểm danh không khớp cuộc họp.', 422);
        }
        $item = $this->meetingAttendanceService->checkinByToken($token);

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận điểm danh qua QR, chờ duyệt.');
    }

    /**
     * Nested route `POST /api/meetings/{meeting}/attendances/mark-absent` — gate participate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @bodyParam note string Lý do vắng (optional). Example: Bị ốm
     */
    public function markAbsentInMeeting(Meeting $meeting, \App\Modules\Meeting\Requests\MarkAbsentMeetingAttendanceRequest $request)
    {
        $item = $this->meetingAttendanceService->markAbsent($meeting->id, $request->validated('note'));

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã ghi nhận báo vắng.');
    }

    /**
     * Nested route `POST /api/meetings/{meeting}/attendances/manual-checkin` — gate manageAttendance (chair/op).
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @bodyParam meeting_participant_id integer required ID đại biểu. Example: 12
     * @bodyParam status string required present | absent. Example: present
     * @bodyParam note string Ghi chú lý do. Example: Bị ốm
     */
    public function manualCheckinInMeeting(Meeting $meeting, \App\Modules\Meeting\Requests\ManualCheckinRequest $request)
    {
        $item = $this->meetingAttendanceService->manualCheckin(
            $meeting->id,
            (int) $request->validated('meeting_participant_id'),
            $request->validated('status'),
            $request->validated('note'),
        );

        return $this->successResource(new MeetingAttendanceResource($item), 'Đã điểm danh hộ đại biểu.');
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/attendances/export` — gate operate.
     *
     * Xuất ra các trường: STT, Tên đại biểu, Chức vụ, Xác nhận tham gia, Đã điểm danh,
     * Đã xác nhận điểm danh, Giờ điểm danh.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function exportInMeeting(Meeting $meeting, FilterRequest $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingAttendanceExport($meeting->id),
            ExportFilename::make('diem-danh-hop'),
        );
    }
}
