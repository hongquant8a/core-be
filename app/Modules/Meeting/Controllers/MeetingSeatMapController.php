<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Requests\AssignMeetingSeatRequest;
use App\Modules\Meeting\Requests\AutoArrangeSeatMapRequest;
use App\Modules\Meeting\Requests\SaveMeetingSeatMapRequest;
use App\Modules\Meeting\Resources\MeetingSeatMapResource;
use App\Modules\Meeting\Services\MeetingSeatMapService;

/**
 * @group Meeting - Sơ đồ chỗ ngồi
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Bố trí sơ đồ chỗ ngồi đại biểu cho từng cuộc họp — 1 cuộc họp có tối đa 1 sơ đồ.
 */
class MeetingSeatMapController extends Controller
{
    public function __construct(private MeetingSeatMapService $service) {}

    /**
     * Xem sơ đồ chỗ ngồi của cuộc họp.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function showInMeeting(Meeting $meeting)
    {
        $seatMap = $this->service->showInMeeting($meeting);
        if (! $seatMap) {
            return $this->success(null, 'Cuộc họp chưa có sơ đồ chỗ ngồi.');
        }

        return $this->successResource(new MeetingSeatMapResource($seatMap));
    }

    /**
     * Lưu cấu hình sơ đồ + sinh lại ghế (không gán người).
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam layout_type string required Kiểu sơ đồ: theater | presidium | curved | ushape. Example: theater
     * @bodyParam config object required Tham số sinh ghế theo kiểu sơ đồ (rows, cols, head, side — tối đa 50). Example: {"rows": 5, "cols": 8}
     * @bodyParam canvas object Kích thước canvas tùy chỉnh — bỏ trống để BE tự tính. Example: {"width": 980, "height": 620}
     * @bodyParam keep_assignments boolean Giữ đại biểu/cờ VIP đã gán ở ghế còn tồn tại sau khi sinh lại. Mặc định true. Example: true
     */
    public function saveInMeeting(SaveMeetingSeatMapRequest $request, Meeting $meeting)
    {
        $seatMap = $this->service->saveInMeeting($meeting, $request->validated());

        return $this->successResource(new MeetingSeatMapResource($seatMap), 'Lưu sơ đồ chỗ ngồi thành công!');
    }

    /**
     * Gán/gỡ đại biểu vào ghế và/hoặc đổi cờ ghế trưởng đoàn·VIP (hỗ trợ bulk).
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam assignments object[] required Danh sách thay đổi theo ghế. Example: [{"seat_id": 1, "meeting_participant_id": 88}]
     * @bodyParam assignments[].seat_id integer required ID ghế. Example: 1
     * @bodyParam assignments[].meeting_participant_id integer Đại biểu gán vào ghế — null để gỡ. Example: 88
     * @bodyParam assignments[].is_vip boolean Đánh dấu/bỏ đánh dấu ghế trưởng đoàn·VIP. Example: true
     */
    public function assignInMeeting(AssignMeetingSeatRequest $request, Meeting $meeting)
    {
        $seatMap = $this->service->assignInMeeting($meeting, $request->validated('assignments'));

        return $this->successResource(new MeetingSeatMapResource($seatMap), 'Cập nhật chỗ ngồi thành công!');
    }

    /**
     * Tự động xếp đại biểu chưa xếp vào ghế còn trống — giữ nguyên chỗ đã xếp tay.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam mode string required Kiểu xếp: rank (chức vụ) | abc (tên A-Z) | random (ngẫu nhiên). Example: rank
     */
    public function autoArrangeInMeeting(AutoArrangeSeatMapRequest $request, Meeting $meeting)
    {
        $seatMap = $this->service->autoArrangeInMeeting($meeting, $request->validated('mode'));

        return $this->successResource(new MeetingSeatMapResource($seatMap), 'Đã tự động xếp chỗ ngồi.');
    }
}
