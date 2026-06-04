<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
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
     * Operator/Chair có thể bổ sung:
     *   - operator_note (discussion): ghi chú thảo luận
     *   - answer_content (question): nội dung trả lời chất vấn
     * Đại biểu KHÔNG sửa được 2 field này (BE tự strip trong prepareForValidation).
     *
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     * @bodyParam type string Loại đăng ký (discussion | question). Example: question
     * @bodyParam content string Nội dung đăng ký. Example: Làm rõ nguyên nhân chậm tiến độ
     * @bodyParam operator_note string Ghi chú thảo luận (operator/chair). Example: Đề xuất được tiếp thu.
     * @bodyParam answer_content string Nội dung trả lời chất vấn (operator/chair). Example: Đã trả lời: phân bổ 200 tỷ.
     * @bodyParam attachment file Tệp đính kèm mới (thay tệp cũ). Example: (binary)
     * @bodyParam remove_attachment boolean Xóa tệp đính kèm hiện tại. Example: false
     * @bodyParam status string Trạng thái đăng ký. Example: completed
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
        \Illuminate\Support\Facades\Gate::authorize('exportReports', $meeting);

        $fileName = $type === 'question' ? ExportFilename::make('chat-van-hop') : ExportFilename::make('thao-luan-hop');

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
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/{reg}` — gate view.
     *
     * Wrapper: nhận thêm `Meeting $meeting` từ URL (route param `{meeting}`) để
     * Laravel controller dispatcher match positional đúng. Logic delegate sang show().
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function showInMeeting(Meeting $meeting, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        return $this->show($meetingDiscussionRegistration);
    }

    /**
     * Nested route `PUT/PATCH /api/meetings/{meeting}/discussion-registrations/{reg}` — gate update.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     *
     * @bodyParam content string Nội dung đăng ký. Example: Cập nhật nội dung
     * @bodyParam operator_note string Ghi chú thảo luận (chỉ chair/op). Example: Đã ghi nhận
     * @bodyParam answer_content string Nội dung trả lời chất vấn (chỉ chair/op). Example: Đã trả lời
     */
    public function updateInMeeting(UpdateMeetingDiscussionRegistrationRequest $request, Meeting $meeting, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        return $this->update($request, $meetingDiscussionRegistration);
    }

    /**
     * Nested route `DELETE /api/meetings/{meeting}/discussion-registrations/{reg}` — gate delete.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function destroyInMeeting(Meeting $meeting, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        return $this->destroy($meetingDiscussionRegistration);
    }

    /**
     * Nested route `PATCH /api/meetings/{meeting}/discussion-registrations/{reg}/complete` — gate complete.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     */
    public function completeInMeeting(Meeting $meeting, MeetingDiscussionRegistration $meetingDiscussionRegistration)
    {
        return $this->complete($meetingDiscussionRegistration);
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/stats` — gate viewParticipant.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function statsInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $filters = array_merge($request->all(), ['meeting_id' => $meeting->id]);

        return $this->success($this->meetingDiscussionRegistrationService->stats($filters, $meeting));
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
        $items = $this->meetingDiscussionRegistrationService->index($filters, (int) ($request->limit ?? 100), $meeting);

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
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/export` — chair/op
     * xuất full list (gate `operate,meeting`, FK pure, không Spatie admin fallback).
     *
     * Xuất ra các trường: STT, Chương trình, Người đăng ký, Thời gian đăng ký, Nội dung,
     * Ghi chú/Nội dung trả lời, Trạng thái, Đính kèm.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam type string required Loại đăng ký (discussion|question). Example: discussion
     * @queryParam meeting_agenda_id integer Lọc theo chương trình họp. Mặc định xuất toàn meeting. Example: 5
     */
    public function exportInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $request->validate([
            'type' => 'required|in:discussion,question',
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
        ]);
        $type = (string) $request->input('type');
        $agendaId = $request->filled('meeting_agenda_id') ? (int) $request->input('meeting_agenda_id') : null;
        $fileName = ExportFilename::make($type === 'question' ? 'chat-van-hop' : 'thao-luan-hop');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingDiscussionRegistrationExport($meeting->id, $type, $agendaId, false),
            $fileName,
        );
    }

    /**
     * Nested route `GET /api/meetings/{meeting}/discussion-registrations/export-mine`.
     *
     * Đại biểu (bao gồm chair/op) tự xuất đăng ký của chính họ. Gate `participate,meeting`
     * (user có role nào đó trong meeting). Service filter theo participant.attendee.user_id
     * = auth user.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam type string required Loại đăng ký (discussion|question). Example: discussion
     * @queryParam meeting_agenda_id integer Lọc theo chương trình họp. Mặc định xuất toàn meeting. Example: 5
     */
    public function exportMineInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $request->validate([
            'type' => 'required|in:discussion,question',
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
        ]);
        $type = (string) $request->input('type');
        $agendaId = $request->filled('meeting_agenda_id') ? (int) $request->input('meeting_agenda_id') : null;
        $slug = $type === 'question' ? 'chat-van-hop-cua-toi' : 'thao-luan-hop-cua-toi';
        $fileName = ExportFilename::make($slug);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Meeting\Exports\MeetingDiscussionRegistrationExport($meeting->id, $type, $agendaId, true),
            $fileName,
        );
    }
}
