<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingPersonalNoteAttachment;
use App\Modules\Meeting\Requests\ReorderMeetingPersonalNoteAttachmentRequest;
use App\Modules\Meeting\Requests\StoreMeetingPersonalNoteAttachmentRequest;
use App\Modules\Meeting\Resources\MeetingPersonalNoteAttachmentResource;
use App\Modules\Meeting\Services\MeetingPersonalNoteAttachmentService;

/**
 * @group Meeting - Tệp ghi chú cá nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý tệp đính kèm cho ghi chú cá nhân của đại biểu.
 */
class MeetingPersonalNoteAttachmentController extends Controller
{
    public function __construct(private MeetingPersonalNoteAttachmentService $meetingPersonalNoteAttachmentService) {}

    /**
     * Danh sách file đính kèm ghi chú cá nhân.
     *
     * @queryParam meeting_personal_note_id integer Lọc theo ghi chú cá nhân. Example: 1
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingPersonalNoteAttachmentService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingPersonalNoteAttachmentResource::collection($items));
    }

    /**
     * Thêm file đính kèm cho ghi chú cá nhân.
     *
     * @bodyParam meeting_personal_note_id integer required ID ghi chú cá nhân. Example: 1
     * @bodyParam file file required Tệp đính kèm. Example: (binary)
     * @bodyParam sort_order integer Thứ tự hiển thị tệp. Example: 1
     */
    public function store(StoreMeetingPersonalNoteAttachmentRequest $request)
    {
        $item = $this->meetingPersonalNoteAttachmentService->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingPersonalNoteAttachmentResource($item), 'Thêm file đính kèm ghi chú thành công!', 201);
    }

    /**
     * Xóa file đính kèm ghi chú cá nhân.
     *
     * @urlParam meetingPersonalNoteAttachment integer required ID file ghi chú cá nhân. Example: 1
     */
    public function destroy(MeetingPersonalNoteAttachment $meetingPersonalNoteAttachment)
    {
        $this->meetingPersonalNoteAttachmentService->destroy($meetingPersonalNoteAttachment);

        return $this->success(null, 'Xóa file đính kèm thành công!');
    }

    /**
     * Sắp xếp thứ tự file đính kèm ghi chú cá nhân.
     *
     * @bodyParam items object[] required Danh sách tệp cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingPersonalNoteAttachmentRequest $request)
    {
        $this->meetingPersonalNoteAttachmentService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp file đính kèm thành công!');
    }
}
