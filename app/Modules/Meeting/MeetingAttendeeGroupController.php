<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusCatalogRequest;
use App\Modules\Meeting\Requests\ChangeStatusCatalogRequest;
use App\Modules\Meeting\Requests\ImportMeetingFileRequest;
use App\Modules\Meeting\Requests\StoreCatalogRequest;
use App\Modules\Meeting\Requests\UpdateCatalogRequest;
use App\Modules\Meeting\Resources\CatalogCollection;
use App\Modules\Meeting\Resources\CatalogResource;
use App\Modules\Meeting\Services\CatalogService;
use App\Modules\Meeting\Services\MeetingAttendeeGroupMembershipService;
use App\Modules\Meeting\Requests\SyncMeetingAttendeeGroupMembersRequest;

/**
 * @group Meeting - Nhóm đại biểu
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý nhóm đại biểu phục vụ mời họp.
 */
class MeetingAttendeeGroupController extends Controller
{
    public function __construct(
        private CatalogService $catalogService,
        private MeetingAttendeeGroupMembershipService $membershipService,
    ) {}

    /**
     * Thống kê nhóm đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên nhóm. Example: tổ đại biểu 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->catalogService->stats(MeetingAttendeeGroup::class, $request->all()));
    }

    /**
     * Danh sách nhóm đại biểu.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên nhóm. Example: tổ đại biểu 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     * @queryParam sort_by string Sắp xếp theo trường. Example: name
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->catalogService->index(MeetingAttendeeGroup::class, $request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new CatalogCollection($items));
    }

    /**
     * Chi tiết nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     */
    public function show(MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        return $this->successResource(new CatalogResource($this->catalogService->show($meetingAttendeeGroup)));
    }

    /**
     * Tạo nhóm đại biểu.
     *
     * @bodyParam name string required Tên nhóm đại biểu. Example: Tổ đại biểu số 1
     * @bodyParam description string Mô tả nhóm đại biểu. Example: Nhóm đại biểu khu vực trung tâm
     * @bodyParam status string Trạng thái nhóm. Example: active
     */
    public function store(StoreCatalogRequest $request)
    {
        $item = $this->catalogService->store(MeetingAttendeeGroup::class, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Tạo nhóm đại biểu thành công!', 201);
    }

    /**
     * Cập nhật nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     * @bodyParam name string Tên nhóm đại biểu. Example: Tổ đại biểu số 2
     * @bodyParam description string Mô tả nhóm đại biểu. Example: Nhóm đại biểu khu vực ngoại thành
     * @bodyParam status string Trạng thái nhóm. Example: inactive
     */
    public function update(UpdateCatalogRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $item = $this->catalogService->update($meetingAttendeeGroup, $request->validated());

        return $this->successResource(new CatalogResource($item), 'Cập nhật nhóm đại biểu thành công!');
    }

    /**
     * Xóa nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     */
    public function destroy(MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $this->catalogService->destroy($meetingAttendeeGroup);

        return $this->success(null, 'Xóa nhóm đại biểu thành công!');
    }

    /**
     * Xóa hàng loạt nhóm đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->catalogService->bulkDestroy(MeetingAttendeeGroup::class, $request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt nhóm đại biểu.
     *
     * @bodyParam ids integer[] required Danh sách ID cần cập nhật trạng thái. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới. Example: active
     */
    public function bulkUpdateStatus(BulkUpdateStatusCatalogRequest $request)
    {
        $this->catalogService->bulkUpdateStatus(MeetingAttendeeGroup::class, $request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái nhóm đại biểu.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm đại biểu. Example: 1
     * @bodyParam status string required Trạng thái mới. Example: inactive
     */
    public function changeStatus(ChangeStatusCatalogRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $item = $this->catalogService->changeStatus($meetingAttendeeGroup, $request->status);

        return $this->successResource(new CatalogResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Xuất Excel nhóm đại biểu.
     *
     * Xuất ra các trường: STT, Tên, Mô tả, Địa chỉ, Google Maps URL, Trạng thái, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID. Cột địa chỉ/Google Maps để trống.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên nhóm. Example: tổ đại biểu 1
     * @queryParam status string Lọc theo trạng thái. Example: active
     */
    public function export(FilterRequest $request)
    {
        return $this->catalogService->export(MeetingAttendeeGroup::class, $request->all(), 'meeting-attendee-groups.xlsx');
    }

    /**
     * Nhập Excel nhóm đại biểu.
     *
     * Cột bắt buộc: name. Cột không bắt buộc: description, status (mặc định active).
     */
    public function import(ImportMeetingFileRequest $request)
    {
        $this->catalogService->import(MeetingAttendeeGroup::class, $request->file('file'));

        return $this->success(null, 'Nhập nhóm đại biểu thành công!');
    }

    /**
     * Tải mẫu import nhóm đại biểu.
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Meeting\Imports\CatalogImport::TEMPLATE_LABELS),
            'import-meeting-attendee-groups-template.xlsx'
        );
    }

    /**
     * Danh sách đại biểu thuộc 1 nhóm — phân trang.
     *
     * Mỗi item trả `id` (pivot record id), `meeting_attendee_id` (entity id), thông tin attendee.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm. Example: 1
     * @queryParam search string Từ khóa tìm theo tên/email. Example: nguyen
     * @queryParam sort_by string Sắp xếp theo trường. Example: created_at
     * @queryParam sort_order string asc|desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 20
     */
    public function attendees(FilterRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        $items = $this->membershipService->listAttendees(
            $meetingAttendeeGroup,
            $request->all(),
            (int) ($request->limit ?? 20),
        );

        $mapped = $items->getCollection()->map(function ($attendee) {
            $user = $attendee->user;

            return [
                'id' => (int) $attendee->pivot->id,                          // pivot record id
                'meeting_attendee_id' => (int) $attendee->id,                // entity id
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->profile?->phone,
                'position_name' => $attendee->position_name,
                'department_name' => $attendee->department_name,
                'status' => $attendee->status,
                'user_id' => $attendee->user_id,
                'added_at' => $attendee->pivot->created_at?->format('H:i:s d/m/Y'),
            ];
        });

        return $this->success([
            'items' => $mapped,
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Đồng bộ danh sách đại biểu trong nhóm (full list — sync mode).
     *
     * BE diff với pivot hiện có → thêm/xoá. Idempotent: gọi lại với cùng IDs không có thay đổi.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm. Example: 1
     * @bodyParam meeting_attendee_ids integer[] required Danh sách full ID đại biểu thuộc nhóm. Example: [5,6,7]
     */
    public function syncAttendees(SyncMeetingAttendeeGroupMembersRequest $request, MeetingAttendeeGroup $meetingAttendeeGroup)
    {
        // FE pattern: pick từ User pool → BE auto find-or-create MeetingAttendee.
        $summary = $this->membershipService->syncByUserIds(
            $meetingAttendeeGroup,
            $request->validated('user_ids'),
        );

        return $this->success($summary, 'Cập nhật danh sách đại biểu thành công.');
    }

    /**
     * Gỡ đại biểu khỏi nhóm bằng user_id — pattern TaskAssignmentDepartment users.
     *
     * @urlParam meetingAttendeeGroup integer required ID nhóm. Example: 1
     * @urlParam userId integer required ID user (User entity, KHÔNG phải attendee_id). Example: 10
     */
    public function removeAttendee(MeetingAttendeeGroup $meetingAttendeeGroup, int $userId)
    {
        $this->membershipService->removeByUserId($meetingAttendeeGroup, $userId);

        return $this->success(null, 'Đã gỡ đại biểu khỏi nhóm.');
    }
}
