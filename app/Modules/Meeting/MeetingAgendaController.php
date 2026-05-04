<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\ReorderMeetingAgendaRequest;
use App\Modules\Meeting\Requests\StoreMeetingAgendaRequest;
use App\Modules\Meeting\Requests\UpdateMeetingAgendaRequest;
use App\Modules\Meeting\Resources\MeetingAgendaCollection;
use App\Modules\Meeting\Resources\MeetingAgendaResource;
use App\Modules\Meeting\Services\MeetingAgendaService;

/**
 * @group Meeting - Chương trình họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý chương trình họp theo cuộc họp, hỗ trợ sắp xếp thứ tự.
 */
class MeetingAgendaController extends Controller
{
    public function __construct(private MeetingAgendaService $meetingAgendaService) {}

    /**
     * Danh sách chương trình họp.
     *
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam parent_id integer Lọc theo agenda cha. Example: 1
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingAgendaService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingAgendaCollection($items));
    }

    /**
     * Chi tiết chương trình họp.
     *
     * @urlParam meetingAgenda integer required ID chương trình họp. Example: 1
     */
    public function show(MeetingAgenda $meetingAgenda)
    {
        return $this->successResource(new MeetingAgendaResource($this->meetingAgendaService->show($meetingAgenda)));
    }

    /**
     * Tạo chương trình họp.
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam title string required Tiêu đề chương trình họp. Example: Khai mạc kỳ họp
     * @bodyParam content string Nội dung chương trình họp. Example: Phát biểu khai mạc và thông qua chương trình
     * @bodyParam parent_id integer ID chương trình cha. Example: 1
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 1
     * @bodyParam start_time datetime Thời gian bắt đầu (Y-m-d H:i:s). Example: 2026-05-01 08:00:00
     * @bodyParam end_time datetime Thời gian kết thúc (Y-m-d H:i:s). Example: 2026-05-01 08:30:00
     */
    public function store(StoreMeetingAgendaRequest $request)
    {
        $item = $this->meetingAgendaService->store($request->validated());

        return $this->successResource(new MeetingAgendaResource($item), 'Tạo chương trình họp thành công!', 201);
    }

    /**
     * Cập nhật chương trình họp.
     *
     * @urlParam meetingAgenda integer required ID chương trình họp. Example: 1
     * @bodyParam title string Tiêu đề chương trình họp. Example: Thảo luận tổ
     * @bodyParam content string Nội dung chương trình họp. Example: Đại biểu thảo luận theo tổ
     * @bodyParam parent_id integer ID chương trình cha. Example: 1
     * @bodyParam sort_order integer Thứ tự hiển thị. Example: 2
     * @bodyParam start_time datetime Thời gian bắt đầu (Y-m-d H:i:s). Example: 2026-05-01 09:00:00
     * @bodyParam end_time datetime Thời gian kết thúc (Y-m-d H:i:s). Example: 2026-05-01 10:30:00
     */
    public function update(UpdateMeetingAgendaRequest $request, MeetingAgenda $meetingAgenda)
    {
        $item = $this->meetingAgendaService->update($meetingAgenda, $request->validated());

        return $this->successResource(new MeetingAgendaResource($item), 'Cập nhật chương trình họp thành công!');
    }

    /**
     * Xóa chương trình họp.
     *
     * @urlParam meetingAgenda integer required ID chương trình họp. Example: 1
     */
    public function destroy(MeetingAgenda $meetingAgenda)
    {
        $this->meetingAgendaService->destroy($meetingAgenda);

        return $this->success(null, 'Xóa chương trình họp thành công!');
    }

    /**
     * Xóa hàng loạt chương trình họp.
     *
     * @bodyParam ids integer[] required Danh sách ID chương trình cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingAgendaService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Sắp xếp lại thứ tự chương trình họp.
     *
     * @bodyParam items object[] required Danh sách chương trình cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingAgendaRequest $request)
    {
        $this->meetingAgendaService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp chương trình họp thành công!');
    }
}
