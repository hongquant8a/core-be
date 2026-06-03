<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Requests\{
    StoreScheduleRequest, UpdateScheduleRequest, ChangeStatusScheduleRequest,
    BulkDestroyScheduleRequest, BulkUpdateStatusScheduleRequest,
    DuplicateScheduleRequest, ReorderScheduleRequest
};
use App\Modules\Scheduling\Resources\{ScheduleCollection, ScheduleResource, DriverScheduleResource};
use App\Modules\Scheduling\Services\ScheduleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Scheduling - Lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý lịch công tác tuần/ngày, hỗ trợ phân hệ Thường trực/Văn phòng, duyệt trạng thái và phân quyền lái xe.
 */
class ScheduleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ScheduleService $scheduleService) {}

    /**
     * Thống kê số lượng lịch công tác theo các trạng thái.
     *
     * @queryParam module_type string Lọc theo phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam search string Tìm kiếm theo nội dung lịch công tác. Example: họp giao ban
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-06-07
     * @queryParam session string Lọc theo buổi (S: Sáng, C: Chiều, T: Tối). Example: S
     */
    public function stats(FilterRequest $request): JsonResponse
    {
        return $this->success($this->scheduleService->stats($request->all()));
    }

    /**
     * Danh sách lịch công tác.
     *
     * @queryParam module_type string Lọc theo phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam search string Tìm kiếm theo nội dung lịch công tác. Example: họp giao ban
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-06-07
     * @queryParam session string Lọc theo buổi (S: Sáng, C: Chiều, T: Tối). Example: S
     * @queryParam status string Lọc theo trạng thái (DRAFT, PENDING, APPROVED, CANCELLED). Example: APPROVED
     * @queryParam view_type string Chế độ xem (personal: Cá nhân tôi chủ trì/tham dự, all: Tất cả lịch được xem, managed: Lịch tôi có quyền quản lý). Example: all
     * @queryParam sort_by string Sắp xếp theo trường (time: theo giờ, position: theo chức vụ chủ trì, manual: sắp xếp thủ công). Example: time
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request): JsonResponse
    {
        return $this->successCollection(
            new ScheduleCollection(
                $this->scheduleService->index($request->all(), (int)($request->limit ?? 20))
            )
        );
    }

    /**
     * Ma trận lịch công tác theo tuần (Weekly Matrix).
     *
     * @queryParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam week_number integer Số thứ tự tuần trong năm. Example: 23
     * @queryParam year integer Năm của tuần cần xem. Example: 2026
     * @queryParam from_date date Lọc từ ngày (Y-m-d) thay cho week_number. Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d) thay cho week_number. Example: 2026-06-07
     * @queryParam status string Lọc theo trạng thái (DRAFT, PENDING, APPROVED, CANCELLED). Example: APPROVED
     */
    public function weeklyMatrix(FilterRequest $request): JsonResponse
    {
        return $this->success($this->scheduleService->weekMatrix($request->all()));
    }

    /**
     * Danh sách các tuần đã có lịch công tác (cho dropdown chọn nhanh).
     *
     * @queryParam module_type string Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     */
    public function weeks(FilterRequest $request): JsonResponse
    {
        return $this->success($this->scheduleService->getWeeks($request->all()));
    }

    /**
     * Chi tiết lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     */
    public function show(Schedule $schedule): JsonResponse
    {
        return $this->successResource(new ScheduleResource($this->scheduleService->show($schedule)));
    }

    /**
     * Tạo mới lịch công tác.
     *
     * @bodyParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @bodyParam date date required Ngày diễn ra sự kiện (Y-m-d). Example: 2026-06-01
     * @bodyParam session string Buổi diễn ra (M: Sáng, A: Chiều, E: Tối). Example: M
     * @bodyParam title string required Tiêu đề lịch công tác. Example: Họp giao ban định kỳ tuần 23
     * @bodyParam content string Nội dung lịch công tác. Example: Nội dung chi tiết cuộc họp
     * @bodyParam host_user_id integer ID người chủ trì (user). Example: 2
     * @bodyParam host_text string Tên người chủ trì nếu không có host_user_id. Example: Lãnh đạo sở
     * @bodyParam location string required Địa điểm họp/công tác. Example: Phòng họp số 1
     * @bodyParam driver_user_id integer ID lái xe (user). Example: 5
     * @bodyParam car_info string Thông tin xe phục vụ. Example: Xe BKS 29A-12345
     * @bodyParam is_important boolean Đánh dấu lịch quan trọng. Example: false
     * @bodyParam status string Trạng thái lịch công tác (DRAFT, PENDING, APPROVED, CANCELLED). Example: DRAFT
     * @bodyParam files file[] Danh sách tài liệu đính kèm.
     * @bodyParam participants array Danh sách thành phần tham dự.
     * @bodyParam reminders array Danh sách mốc nhắc lịch.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $files = array_merge(
            is_array($request->file('files')) ? $request->file('files') : ($request->hasFile('files') ? [$request->file('files')] : []),
            is_array($request->file('attachments')) ? $request->file('attachments') : ($request->hasFile('attachments') ? [$request->file('attachments')] : [])
        );

        $schedule = $this->scheduleService->store(
            $request->validated(),
            $files,
            $request->input('participants', []),
            $request->input('reminders', [])
        );
        return $this->successResource(new ScheduleResource($schedule), 'Tạo lịch công tác thành công!', 201);
    }

    /**
     * Cập nhật lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác cần sửa. Example: 1
     * @bodyParam module_type string Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @bodyParam date date Ngày diễn ra sự kiện (Y-m-d). Example: 2026-06-01
     * @bodyParam session string Buổi diễn ra (M: Sáng, A: Chiều, E: Tối). Example: M
     * @bodyParam title string Tiêu đề lịch công tác. Example: Họp giao ban định kỳ tuần 23
     * @bodyParam content string Nội dung lịch công tác. Example: Nội dung chi tiết cuộc họp
     * @bodyParam host_user_id integer ID người chủ trì (user). Example: 2
     * @bodyParam host_text string Tên người chủ trì nếu không có host_user_id. Example: Lãnh đạo sở
     * @bodyParam location string Địa điểm họp/công tác. Example: Phòng họp số 1
     * @bodyParam driver_user_id integer ID lái xe (user). Example: 5
     * @bodyParam car_info string Thông tin xe phục vụ. Example: Xe BKS 29A-12345
     * @bodyParam is_important boolean Đánh dấu lịch quan trọng. Example: false
     * @bodyParam status string Trạng thái lịch công tác (DRAFT, PENDING, APPROVED, CANCELLED). Example: DRAFT
     * @bodyParam files file[] Danh sách tài liệu đính kèm mới.
     * @bodyParam participants array Danh sách thành phần tham dự mới.
     * @bodyParam reminders array Danh sách mốc nhắc lịch mới.
     * @bodyParam remove_media_ids array Danh sách ID tài liệu đính kèm cần xóa.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $files = array_merge(
            is_array($request->file('files')) ? $request->file('files') : ($request->hasFile('files') ? [$request->file('files')] : []),
            is_array($request->file('attachments')) ? $request->file('attachments') : ($request->hasFile('attachments') ? [$request->file('attachments')] : [])
        );

        $schedule = $this->scheduleService->update(
            $schedule,
            $request->validated(),
            $files,
            $request->has('participants') ? $request->input('participants') : null,
            $request->has('reminders') ? $request->input('reminders') : null,
            $request->input('remove_media_ids', [])
        );
        return $this->successResource(new ScheduleResource($schedule), 'Cập nhật lịch thành công!');
    }

    /**
     * Xóa lịch công tác (Soft Delete).
     *
     * @urlParam schedule integer required ID lịch công tác cần xóa. Example: 1
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $this->scheduleService->destroy($schedule);
        return $this->success(null, 'Xóa lịch công tác thành công!');
    }

    /**
     * Xóa hàng loạt lịch công tác.
     *
     * @bodyParam ids array required Danh sách ID lịch công tác cần xóa. Example: [1, 2]
     */
    public function bulkDestroy(BulkDestroyScheduleRequest $request): JsonResponse
    {
        $this->scheduleService->bulkDestroy($request->input('ids'));
        return $this->success(null, 'Xóa nhiều lịch công tác thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt lịch công tác.
     *
     * @bodyParam ids array required Danh sách ID lịch công tác cần cập nhật. Example: [1, 2]
     * @bodyParam status string required Trạng thái mới (DRAFT, PENDING, APPROVED, CANCELLED). Example: APPROVED
     */
    public function bulkUpdateStatus(BulkUpdateStatusScheduleRequest $request): JsonResponse
    {
        $this->scheduleService->bulkUpdateStatus($request->input('ids'), $request->input('status'));
        return $this->success(null, 'Cập nhật trạng thái nhiều lịch công tác thành công!');
    }

    /**
     * Thay đổi trạng thái lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     * @bodyParam status string required Trạng thái mới (DRAFT, PENDING, APPROVED, CANCELLED). Example: APPROVED
     */
    public function changeStatus(ChangeStatusScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->changeStatus($schedule, $request->input('status'));
        return $this->successResource(new ScheduleResource($schedule), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Duyệt lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     */
    public function approve(Schedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->approve($schedule);
        return $this->successResource(new ScheduleResource($schedule), 'Duyệt lịch công tác thành công!');
    }

    /**
     * Từ chối duyệt lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     * @bodyParam rejection_note string required Lý do từ chối duyệt. Example: Thiếu nội dung chi tiết
     */
    public function reject(Request $request, Schedule $schedule): JsonResponse
    {
        $schedule = $this->scheduleService->reject($schedule, $request->input('rejection_note', ''));
        return $this->successResource(new ScheduleResource($schedule), 'Từ chối lịch công tác thành công!');
    }

    /**
     * Sao chép lịch công tác sang ngày mới.
     *
     * @urlParam schedule integer required ID lịch công tác gốc. Example: 1
     * @bodyParam date date required Ngày mới (Y-m-d). Example: 2026-06-08
     */
    public function duplicate(DuplicateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $newSchedule = $this->scheduleService->duplicate($schedule, $request->input('date'));
        return $this->successResource(new ScheduleResource($newSchedule), 'Sao chép lịch công tác thành công!', 201);
    }

    /**
     * Thay đổi thứ tự sắp xếp của danh sách lịch công tác.
     *
     * @bodyParam ordered_ids array required Danh sách ID lịch công tác theo thứ tự mong muốn. Example: [3, 1, 2]
     */
    public function reorder(ReorderScheduleRequest $request): JsonResponse
    {
        $this->scheduleService->reorder($request->input('ordered_ids'));
        return $this->success(null, 'Sắp xếp lịch công tác thành công!');
    }

    /**
     * Xuất dữ liệu lịch công tác ra file Excel.
     *
     * @queryParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam week_number integer Số thứ tự tuần. Example: 23
     * @queryParam year integer Năm. Example: 2026
     */
    public function export(FilterRequest $request)
    {
        return $this->scheduleService->export($request->all());
    }

    /**
     * Xuất dữ liệu lịch công tác ra file PDF.
     *
     * @queryParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam week_number integer Số thứ tự tuần. Example: 23
     * @queryParam year integer Năm. Example: 2026
     */
    public function exportPdf(FilterRequest $request)
    {
        return $this->scheduleService->exportPdf($request->all());
    }

    /**
     * Xuất dữ liệu lịch công tác ra file Word (.docx).
     *
     * @queryParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam week_number integer Số thứ tự tuần. Example: 23
     * @queryParam year integer Năm. Example: 2026
     */
    public function exportWord(FilterRequest $request)
    {
        return $this->scheduleService->exportWord($request->all());
    }

    /**
     * Danh sách lịch công tác được gán riêng cho Lái xe hiện tại.
     *
     * @queryParam from date Ngày bắt đầu (Y-m-d). Example: 2026-06-01
     * @queryParam to date Ngày kết thúc (Y-m-d). Example: 2026-06-07
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 20
     */
    public function driverIndex(FilterRequest $request): JsonResponse
    {
        $this->authorize('driverViewAny', Schedule::class);
        $filters = $request->all();
        $filters['status'] = \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value;
        $filters['driver_id'] = auth()->id();

        $schedules = Schedule::with(['host'])
            ->filter($filters)
            ->paginate((int)($request->limit ?? 20));

        return $this->successCollection(DriverScheduleResource::collection($schedules));
    }

    /**
     * Chi tiết chuyến công tác phân công cho Lái xe.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     */
    public function driverShow(Schedule $schedule): JsonResponse
    {
        $this->authorize('driverView', $schedule);
        return $this->successResource(new DriverScheduleResource($schedule->load('host')));
    }
}
