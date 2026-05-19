<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\ImportMeetingFileRequest;
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
     * Danh sách user trong tổ chức để chọn khi tạo đại biểu (dropdown).
     *
     * Trả `[{id, name, email, phone}]`. Dùng chung permission `meeting-attendees.store`
     * — FE không cần `users.index` để mở dropdown này, tránh CASL conflict.
     * Loại trừ user đã được link với một đại biểu trong tổ chức hiện tại.
     *
     * @queryParam search string Tìm theo tên/email. Example: nguyen
     * @queryParam limit integer Giới hạn số bản ghi (mặc định 50). Example: 20
     */
    public function userOptions(FilterRequest $request)
    {
        return $this->success($this->meetingAttendeeService->userOptions($request->all()));
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
     * Tạo đại biểu — link tới user hiện có trong tổ chức (1-1, unique theo org).
     *
     * @bodyParam user_id integer required ID user. Example: 12
     * @bodyParam meeting_attendee_group_id integer ID nhóm đại biểu. Example: 1
     * @bodyParam position_name string Chức vụ override (mặc định lấy từ user). Example: Phó chủ tịch
     * @bodyParam department_name string Đơn vị override. Example: HĐND TP
     * @bodyParam status string Trạng thái đại biểu. Example: active
     * @bodyParam note string Ghi chú. Example: Đại biểu mời thường xuyên
     */
    public function store(StoreMeetingAttendeeRequest $request)
    {
        $item = $this->meetingAttendeeService->store($request->validated());

        return $this->successResource(new MeetingAttendeeResource($item), 'Tạo đại biểu thành công!', 201);
    }

    /**
     * Cập nhật đại biểu (chỉ trường meeting-specific; không đổi user_id).
     *
     * @urlParam meetingAttendee integer required ID đại biểu. Example: 1
     * @bodyParam meeting_attendee_group_id integer ID nhóm đại biểu. Example: 2
     * @bodyParam position_name string Chức vụ override. Example: Trưởng ban
     * @bodyParam department_name string Đơn vị override. Example: UBND quận
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

    /**
     * Xuất Excel đại biểu.
     *
     * Xuất ra các trường: STT, Họ tên, Nhóm đại biểu, Chức vụ, Đơn vị, Email, Số điện thoại, Trạng thái, Ghi chú, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên/email/đơn vị. Example: nguyen van a
     * @queryParam meeting_attendee_group_id integer Lọc theo nhóm đại biểu. Example: 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function export(FilterRequest $request)
    {
        return $this->meetingAttendeeService->export($request->all());
    }

    /**
     * Nhập Excel đại biểu.
     *
     * Cột bắt buộc: name. Cột không bắt buộc: position_name, department_name, email, phone, note, status (mặc định active).
     */
    public function import(ImportMeetingFileRequest $request)
    {
        $this->meetingAttendeeService->import($request->file('file'));

        return $this->success(null, 'Nhập đại biểu thành công!');
    }

    /**
     * Tải mẫu import đại biểu.
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Meeting\Imports\MeetingAttendeeImport::TEMPLATE_LABELS, \App\Modules\Meeting\Imports\MeetingAttendeeImport::TEMPLATE_EXAMPLES),
            'import-meeting-attendees-template.xlsx'
        );
    }
}
