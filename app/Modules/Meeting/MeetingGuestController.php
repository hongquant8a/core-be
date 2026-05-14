<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingGuest;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\ImportMeetingFileRequest;
use App\Modules\Meeting\Requests\StoreMeetingGuestRequest;
use App\Modules\Meeting\Requests\UpdateMeetingGuestRequest;
use App\Modules\Meeting\Resources\MeetingGuestResource;
use App\Modules\Meeting\Services\MeetingGuestService;

/**
 * @group Meeting - Khách mời
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Danh bạ khách mời PER ORG. Khách mời KHÔNG có tài khoản — chỉ dùng để gửi
 * thư mời (email/SMS) khi meeting publish. Liên kết với meeting qua `guest_ids`
 * trong payload tạo/sửa meeting.
 */
class MeetingGuestController extends Controller
{
    public function __construct(private MeetingGuestService $service) {}

    /**
     * Thống kê khách mời.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên/email/phone. Example: nguyễn
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->service->stats($request->all()));
    }

    /**
     * Danh sách khách mời.
     *
     * @queryParam search string Từ khóa tìm kiếm (name/email/phone). Example: nguyễn
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(MeetingGuestResource::collection($items));
    }

    /**
     * Chi tiết khách mời.
     *
     * @urlParam meetingGuest integer required ID khách mời. Example: 1
     */
    public function show(MeetingGuest $meetingGuest)
    {
        return $this->successResource(new MeetingGuestResource($this->service->show($meetingGuest)));
    }

    /**
     * Tạo khách mời.
     *
     * @bodyParam name string required Họ tên. Example: Nguyễn Văn A
     * @bodyParam email string required Email. Example: a@example.com
     * @bodyParam phone string required Số điện thoại. Example: 0901234567
     * @bodyParam note string Ghi chú. Example: Đại diện Sở
     * @bodyParam status string Trạng thái. Example: active
     */
    public function store(StoreMeetingGuestRequest $request)
    {
        $item = $this->service->store($request->validated());

        return $this->successResource(new MeetingGuestResource($item), 'Tạo khách mời thành công!', 201);
    }

    /**
     * Cập nhật khách mời.
     *
     * @urlParam meetingGuest integer required ID khách mời. Example: 1
     * @bodyParam name string Họ tên. Example: Nguyễn Văn A
     * @bodyParam email string Email. Example: a@example.com
     * @bodyParam phone string Số điện thoại. Example: 0901234567
     * @bodyParam note string Ghi chú. Example: Đại diện Sở
     * @bodyParam status string Trạng thái. Example: inactive
     */
    public function update(UpdateMeetingGuestRequest $request, MeetingGuest $meetingGuest)
    {
        $item = $this->service->update($meetingGuest, $request->validated());

        return $this->successResource(new MeetingGuestResource($item), 'Cập nhật khách mời thành công!');
    }

    /**
     * Xóa khách mời.
     *
     * @urlParam meetingGuest integer required ID khách mời. Example: 1
     */
    public function destroy(MeetingGuest $meetingGuest)
    {
        $this->service->destroy($meetingGuest);

        return $this->success(null, 'Xóa khách mời thành công!');
    }

    /**
     * Xóa hàng loạt khách mời.
     *
     * @bodyParam ids integer[] required Danh sách ID. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->service->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt khách mời.
     *
     * @bodyParam ids integer[] required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->service->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái khách mời.
     *
     * @urlParam meetingGuest integer required ID khách mời. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingGuest $meetingGuest)
    {
        $item = $this->service->changeStatus($meetingGuest, $request->status);

        return $this->successResource(new MeetingGuestResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel khách mời.
     *
     * Xuất ra các trường: STT, Họ tên, Email, Số điện thoại, Ghi chú, Trạng thái, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.
     *
     * @queryParam search string Từ khóa tìm kiếm. Example: nguyễn
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function export(FilterRequest $request)
    {
        return $this->service->export($request->all(), 'export__khach-moi.xlsx');
    }

    /**
     * Nhập Excel khách mời.
     *
     * Cột bắt buộc: name, email, phone. Cột không bắt buộc: note, status (mặc định active).
     */
    public function import(ImportMeetingFileRequest $request)
    {
        \Maatwebsite\Excel\Facades\Excel::import(
            new \App\Modules\Meeting\Imports\MeetingGuestImport(),
            $request->file('file'),
        );

        return $this->success(null, 'Nhập khách mời thành công!');
    }

    /**
     * Tải mẫu import khách mời.
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Meeting\Imports\MeetingGuestImport::TEMPLATE_LABELS),
            'import-meeting-guests-template.xlsx'
        );
    }
}
