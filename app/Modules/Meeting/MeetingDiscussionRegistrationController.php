<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
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
 *
 * Ownership theo spec phòng họp không giấy:
 *   - Đại biểu: store/update/destroy đăng ký của chính họ. Auth user không phải đại biểu
 *     của meeting → 404 (không tìm thấy đăng ký).
 *   - Chủ trì + Thư ký/Điều hành: PATCH /start (gọi đại biểu) + /complete (đánh dấu xong)
 *     — sẽ thêm ở Sprint 1 với middleware meeting.role.
 *   - index/show/stats: ai xem được meeting đều xem được.
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
     * @queryParam my boolean Chỉ trả về đăng ký của user đang login (resolve user→attendee→participant). Example: true
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
     * Đại biểu đăng ký thảo luận/chất vấn — auto-derive participant từ auth user.
     *
     * BE tự tìm participant của user trong cuộc họp; user không phải đại biểu của
     * meeting → 404. FE chỉ cần gửi meeting_id + type + content (+ attachment optional).
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_agenda_id integer ID chương trình họp gắn đăng ký (optional). Example: 2
     * @bodyParam type string required Loại đăng ký (discussion | question). Example: discussion
     * @bodyParam content string required Nội dung đăng ký. Example: Đề xuất nhóm giải pháp trọng tâm
     * @bodyParam attachment file Tệp đính kèm (slide / văn bản tham chiếu) — qua MediaService, ≤10MB. Example: (binary)
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 1
     */
    public function store(StoreMeetingDiscussionRegistrationRequest $request)
    {
        $item = $this->meetingDiscussionRegistrationService->store($request->validated(), $request->file('attachment'));

        return $this->successResource(new MeetingDiscussionRegistrationResource($item), 'Đăng ký thảo luận/chất vấn thành công!', 201);
    }

    /**
     * Cập nhật đăng ký thảo luận/chất vấn.
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     * @bodyParam discussion_type string Loại đăng ký. Example: question
     * @bodyParam topic string Chủ đề đăng ký. Example: Chất vấn tiến độ dự án
     * @bodyParam content string Nội dung đăng ký. Example: Làm rõ nguyên nhân chậm tiến độ
     * @bodyParam attachment file Tệp đính kèm mới (thay tệp cũ). Example: (binary)
     * @bodyParam remove_attachment boolean Xóa tệp đính kèm hiện tại. Example: false
     * @bodyParam status string Trạng thái đăng ký. Example: approved
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 2
     */
    public function update(UpdateMeetingDiscussionRegistrationRequest $request, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        $item = $this->meetingDiscussionRegistrationService->update($meetingDiscussionRegistration, $request->validated(), $request->file('attachment'));

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
     * Operator đánh dấu hoàn thành lượt phát biểu — chuyển trạng thái registered -> completed.
     *
     * Spec section 7.3: điều hành đánh dấu "Đã thảo luận" hoặc "Đã chất vấn".
     * State machine binary: chỉ có Registered + Completed. Set completed_at = now().
     * Đăng ký đã completed → 422.
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function complete(MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        $item = $this->meetingDiscussionRegistrationService->complete($meetingDiscussionRegistration);

        return $this->successResource(new MeetingDiscussionRegistrationResource($item), 'Đã đánh dấu hoàn thành lượt phát biểu.');
    }

    /**
     * Sắp xếp thứ tự đăng ký thảo luận/chất vấn.
     *
     * @bodyParam items object[] required Danh sách đăng ký cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    /**
     * Xuất danh sách thảo luận hoặc chất vấn của 1 cuộc họp ra Excel.
     *
     * Auth-only, không qua Spatie permission. Gate qua MeetingPolicy::operate
     * (chair/operator của meeting) — đại biểu thường không được export.
     *
     * Xuất ra các trường: STT, Chương trình, Người đăng ký, Thời gian đăng ký, Nội dung, Trạng thái.
     *
     * @queryParam meeting_id integer required ID cuộc họp. Example: 1
     * @queryParam type string required Loại đăng ký (discussion|question). Example: discussion
     */
    public function export(FilterRequest $request)
    {
        $request->validate([
            'meeting_id' => 'required|integer|exists:meetings,id',
            'type' => 'required|in:discussion,question',
        ]);

        $meetingId = (int) $request->input('meeting_id');
        $type = (string) $request->input('type');

        $meeting = \App\Modules\Meeting\Models\Meeting::findOrFail($meetingId);
        \Illuminate\Support\Facades\Gate::authorize('operate', $meeting);

        $fileName = $type === 'question' ? 'export__chat-van-hop.xlsx' : 'export__thao-luan-hop.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingDiscussionRegistrationExport($meetingId, $type),
            $fileName,
        );
    }

    public function reorder(ReorderMeetingDiscussionRegistrationRequest $request)
    {
        $this->meetingDiscussionRegistrationService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp danh sách đăng ký thành công!');
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/stats` — gate viewParticipant.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function statsInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $filters = array_merge($request->all(), ['meeting_id' => $meeting->id]);

        return $this->success($this->meetingDiscussionRegistrationService->stats($filters));
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations` — gate viewParticipant.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam type string Lọc theo loại đăng ký. Example: discussion
     * @queryParam status string Lọc theo trạng thái đăng ký. Example: registered
     * @queryParam my boolean Chỉ trả về đăng ký của user đang login. Example: true
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 100
     */
    public function indexInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $filters = array_merge($request->all(), ['meeting_id' => $meeting->id]);
        $items = $this->meetingDiscussionRegistrationService->index($filters, (int) ($request->limit ?? 100));

        return $this->successCollection(MeetingDiscussionRegistrationResource::collection($items));
    }

    /**
     * Nested route `POST /api/meetings/{meeting}/discussion-registrations` — gate participate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @bodyParam meeting_agenda_id integer ID chương trình họp (optional). Example: 2
     * @bodyParam type string required Loại đăng ký (discussion | question). Example: discussion
     * @bodyParam content string required Nội dung đăng ký. Example: Đề xuất giải pháp
     * @bodyParam attachment file Tệp đính kèm (≤10MB).
     */
    public function storeInMeeting(Meeting $meeting, StoreMeetingDiscussionRegistrationRequest $request)
    {
        $validated = $request->validated();
        $validated['meeting_id'] = $meeting->id;
        $item = $this->meetingDiscussionRegistrationService->store($validated, $request->file('attachment'));

        return $this->successResource(new MeetingDiscussionRegistrationResource($item), 'Đăng ký thảo luận/chất vấn thành công!', 201);
    }

    /**
     * Nested route `PATCH /api/meetings/{meeting}/discussion-registrations/reorder` — gate operate.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @bodyParam items object[] required [{"id":1,"sort_order":1}, ...]
     */
    public function reorderInMeeting(Meeting $meeting, ReorderMeetingDiscussionRegistrationRequest $request)
    {
        $this->meetingDiscussionRegistrationService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp danh sách đăng ký thành công!');
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/export` — gate operate.
     *
     * Xuất ra các trường: STT, Chương trình, Người đăng ký, Thời gian đăng ký, Nội dung, Trạng thái.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam type string required Loại đăng ký (discussion|question). Example: discussion
     */
    public function exportInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $request->validate([
            'type' => 'required|in:discussion,question',
        ]);
        $type = (string) $request->input('type');
        $fileName = $type === 'question' ? 'export__chat-van-hop.xlsx' : 'export__thao-luan-hop.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingDiscussionRegistrationExport($meeting->id, $type),
            $fileName,
        );
    }
}
