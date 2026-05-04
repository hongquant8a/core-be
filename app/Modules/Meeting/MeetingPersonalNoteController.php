<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingPersonalNote;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\ReorderMeetingPersonalNoteRequest;
use App\Modules\Meeting\Requests\StoreMeetingPersonalNoteRequest;
use App\Modules\Meeting\Requests\UpdateMeetingPersonalNoteRequest;
use App\Modules\Meeting\Resources\MeetingPersonalNoteResource;
use App\Modules\Meeting\Services\MeetingPersonalNoteService;

/**
 * @group Meeting - Ghi chú cá nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý ghi chú cá nhân của đại biểu trong cuộc họp.
 */
class MeetingPersonalNoteController extends Controller
{
    public function __construct(private MeetingPersonalNoteService $meetingPersonalNoteService) {}

    /**
     * Danh sách ghi chú cá nhân.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_participant_id integer Lọc theo người tham dự. Example: 1
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingPersonalNoteService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingPersonalNoteResource::collection($items));
    }

    /**
     * Chi tiết ghi chú cá nhân.
     *
     * @urlParam meetingPersonalNote integer required ID ghi chú cá nhân. Example: 1
     */
    public function show(MeetingPersonalNote $meetingPersonalNote)
    {
        return $this->successResource(new MeetingPersonalNoteResource($this->meetingPersonalNoteService->show($meetingPersonalNote)));
    }

    /**
     * Tạo ghi chú cá nhân.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam meeting_participant_id integer required ID người tham dự. Example: 1
     * @bodyParam title string required Tiêu đề ghi chú. Example: Ý kiến tại phiên thảo luận
     * @bodyParam content string required Nội dung ghi chú. Example: Đề xuất bổ sung chỉ tiêu đào tạo
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 1
     */
    public function store(StoreMeetingPersonalNoteRequest $request)
    {
        $item = $this->meetingPersonalNoteService->store($request->validated());

        return $this->successResource(new MeetingPersonalNoteResource($item), 'Tạo ghi chú cá nhân thành công!', 201);
    }

    /**
     * Cập nhật ghi chú cá nhân.
     *
     * @urlParam meetingPersonalNote integer required ID ghi chú cá nhân. Example: 1
     * @bodyParam title string Tiêu đề ghi chú. Example: Ý kiến đã điều chỉnh
     * @bodyParam content string Nội dung ghi chú. Example: Cập nhật đề xuất sau phiên giải trình
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 2
     */
    public function update(UpdateMeetingPersonalNoteRequest $request, MeetingPersonalNote $meetingPersonalNote)
    {
        $item = $this->meetingPersonalNoteService->update($meetingPersonalNote, $request->validated());

        return $this->successResource(new MeetingPersonalNoteResource($item), 'Cập nhật ghi chú cá nhân thành công!');
    }

    /**
     * Xóa ghi chú cá nhân.
     *
     * @urlParam meetingPersonalNote integer required ID ghi chú cá nhân. Example: 1
     */
    public function destroy(MeetingPersonalNote $meetingPersonalNote)
    {
        $this->meetingPersonalNoteService->destroy($meetingPersonalNote);

        return $this->success(null, 'Xóa ghi chú cá nhân thành công!');
    }

    /**
     * Xóa hàng loạt ghi chú cá nhân.
     *
     * @bodyParam ids integer[] required Danh sách ID ghi chú cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingPersonalNoteService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Sắp xếp lại thứ tự ghi chú cá nhân.
     *
     * @bodyParam items object[] required Danh sách ghi chú cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingPersonalNoteRequest $request)
    {
        $this->meetingPersonalNoteService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp ghi chú cá nhân thành công!');
    }
}
