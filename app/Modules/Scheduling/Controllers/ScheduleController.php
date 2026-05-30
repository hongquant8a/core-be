<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Requests\StoreScheduleRequest;
use App\Modules\Scheduling\Requests\UpdateScheduleRequest;
use App\Modules\Scheduling\Requests\BulkDestroyScheduleRequest;
use App\Modules\Scheduling\Requests\BulkUpdateStatusScheduleRequest;
use App\Modules\Scheduling\Requests\ChangeStatusScheduleRequest;
use App\Modules\Scheduling\Requests\ImportScheduleRequest;
use App\Modules\Scheduling\Resources\DriverScheduleResource;
use App\Modules\Scheduling\Resources\ScheduleResource;
use App\Modules\Scheduling\Services\ScheduleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
    public function stats(FilterRequest $request)
    {
        $this->authorize('viewAny', Schedule::class);

        $statistics = $this->scheduleService->stats($request->all());

        return $this->success($statistics);
    }

    /**
     * Danh sách lịch công tác.
     *
     * @queryParam module_type string Lọc theo phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam search string Tìm kiếm theo nội dung lịch công tác. Example: họp giao ban
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-06-07
     * @queryParam session string Lọc theo buổi (S: Sáng, C: Chiều, T: Tối). Example: S
     * @queryParam status integer Lọc theo trạng thái (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy). Example: 2
     * @queryParam view_type string Chế độ xem (personal: Cá nhân tôi chủ trì/tham dự, all: Tất cả lịch được xem, managed: Lịch tôi có quyền quản lý). Example: all
     * @queryParam sort_by string Sắp xếp theo trường (time: theo giờ, position: theo chức vụ chủ trì, manual: sắp xếp thủ công). Example: time
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', Schedule::class);

        $limit = (int) ($request->limit ?? 10);
        $schedules = $this->scheduleService->index($request->all(), $limit);

        // Check if user is driver and transform appropriately
        $user = auth()->user();
        if ($user && $user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            return $this->successCollection(DriverScheduleResource::collection($schedules));
        }

        return $this->successCollection(ScheduleResource::collection($schedules));
    }

    /**
     * Ma trận lịch công tác theo tuần (Weekly Matrix).
     *
     * @queryParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam week_number integer Số thứ tự tuần trong năm. Example: 23
     * @queryParam year integer Năm của tuần cần xem. Example: 2026
     * @queryParam from_date date Lọc từ ngày (Y-m-d) thay cho week_number. Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d) thay cho week_number. Example: 2026-06-07
     * @queryParam status integer Lọc theo trạng thái (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy). Example: 2
     */
    public function weeklyMatrix(FilterRequest $request)
    {
        $this->authorize('viewAny', Schedule::class);

        $schedules = $this->scheduleService->weeklyMatrix($request->all());

        // Check if user is driver and transform appropriately
        $user = auth()->user();
        if ($user && $user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            return $this->success(DriverScheduleResource::collection($schedules));
        }

        return $this->success(ScheduleResource::collection($schedules));
    }

    /**
     * Chi tiết lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     */
    public function show(Schedule $schedule)
    {
        $this->authorize('view', $schedule);

        $schedule = $this->scheduleService->show($schedule);

        $user = auth()->user();
        if ($user && $user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            return $this->successResource(new DriverScheduleResource($schedule));
        }

        return $this->successResource(new ScheduleResource($schedule));
    }

    /**
     * Tạo mới lịch công tác.
     *
     * @bodyParam module_type string required Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @bodyParam event_date date required Ngày diễn ra sự kiện (Y-m-d). Example: 2026-06-01
     * @bodyParam start_time string required Giờ bắt đầu (H:i). Example: 08:00
     * @bodyParam end_time string Giờ kết thúc (H:i). Example: 10:00
     * @bodyParam session string Buổi diễn ra (S: Sáng, C: Chiều, T: Tối). Example: S
     * @bodyParam content string required Nội dung lịch công tác. Example: Họp giao ban định kỳ tuần 23
     * @bodyParam host_id integer ID người chủ trì (user). Example: 2
     * @bodyParam host_text string Tên người chủ trì nếu không có host_id. Example: Lãnh đạo sở
     * @bodyParam participants_text string Thành phần tham dự. Example: Toàn thể cán bộ công nhân viên
     * @bodyParam location string required Địa điểm họp/công tác. Example: Phòng họp số 1
     * @bodyParam driver_id integer ID lái xe (user). Example: 5
     * @bodyParam car_info string Thông tin xe phục vụ. Example: Xe BKS 29A-12345
     * @bodyParam nature string required Tính chất công việc (HOST: Chủ trì, ATTEND: Tham dự). Example: HOST
     * @bodyParam is_important boolean Đánh dấu lịch quan trọng. Example: false
     * @bodyParam reminder_preset_id integer ID mốc nhắc lịch mặc định. Example: 1
     * @bodyParam attachments file[] Danh sách tài liệu đính kèm.
     */
    public function store(StoreScheduleRequest $request)
    {
        $this->authorize('create', Schedule::class);

        $schedule = $this->scheduleService->store(
            $request->validated(),
            $request->file('attachments', [])
        );

        return $this->successResource(new ScheduleResource($schedule), 'Tạo lịch công tác thành công!', 201);
    }

    /**
     * Cập nhật lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác cần sửa. Example: 1
     * @bodyParam module_type string Phân hệ (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @bodyParam event_date date Ngày diễn ra sự kiện (Y-m-d). Example: 2026-06-01
     * @bodyParam start_time string Giờ bắt đầu (H:i). Example: 08:00
     * @bodyParam end_time string Giờ kết thúc (H:i). Example: 10:00
     * @bodyParam session string Buổi diễn ra (S: Sáng, C: Chiều, T: Tối). Example: S
     * @bodyParam content string Nội dung lịch công tác. Example: Họp giao ban định kỳ tuần 23
     * @bodyParam host_id integer ID người chủ trì (user). Example: 2
     * @bodyParam host_text string Tên người chủ trì nếu không có host_id. Example: Lãnh đạo sở
     * @bodyParam participants_text string Thành phần tham dự. Example: Toàn thể cán bộ công nhân viên
     * @bodyParam location string Địa điểm họp/công tác. Example: Phòng họp số 1
     * @bodyParam driver_id integer ID lái xe (user). Example: 5
     * @bodyParam car_info string Thông tin xe phục vụ. Example: Xe BKS 29A-12345
     * @bodyParam nature string Tính chất công việc (HOST: Chủ trì, ATTEND: Tham dự). Example: HOST
     * @bodyParam is_important boolean Đánh dấu lịch quan trọng. Example: false
     * @bodyParam reminder_preset_id integer ID mốc nhắc lịch mặc định. Example: 1
     * @bodyParam attachments file[] Danh sách tài liệu đính kèm mới.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $updatedSchedule = $this->scheduleService->update(
            $schedule,
            $request->validated(),
            $request->file('attachments', [])
        );

        return $this->successResource(new ScheduleResource($updatedSchedule), 'Cập nhật lịch công tác thành công!');
    }

    /**
     * Xóa lịch công tác (Soft Delete).
     *
     * @urlParam schedule integer required ID lịch công tác cần xóa. Example: 1
     */
    public function destroy(Schedule $schedule)
    {
        $this->authorize('delete', $schedule);

        $this->scheduleService->destroy($schedule);

        return $this->success(null, 'Xóa lịch công tác thành công!');
    }

    /**
     * Khôi phục lịch công tác đã xóa.
     *
     * @urlParam id integer required ID lịch công tác đã xóa cần khôi phục. Example: 1
     */
    public function restore(int $id)
    {
        $this->authorize('create', Schedule::class);

        $schedule = $this->scheduleService->restore($id);

        return $this->successResource(new ScheduleResource($schedule), 'Khôi phục lịch công tác thành công!');
    }

    /**
     * Thay đổi trạng thái lịch công tác.
     *
     * @urlParam schedule integer required ID lịch công tác. Example: 1
     * @bodyParam status integer required Trạng thái mới. Example: 2
     */
    public function changeStatus(ChangeStatusScheduleRequest $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $updatedSchedule = $this->scheduleService->changeStatus($schedule, $request->status);

        return $this->successResource(new ScheduleResource($updatedSchedule), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Cập nhật trạng thái hàng loạt lịch công tác.
     *
     * @bodyParam ids array required Danh sách ID lịch công tác cần cập nhật. Example: [1, 2]
     * @bodyParam status integer required Trạng thái mới. Example: 2
     */
    public function bulkUpdateStatus(BulkUpdateStatusScheduleRequest $request)
    {
        $this->authorize('updateStatus', Schedule::class);

        $schedules = Schedule::whereIn('id', $request->ids)->get();
        foreach ($schedules as $schedule) {
            $this->authorize('update', $schedule);
        }

        $this->scheduleService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Xóa hàng loạt lịch công tác.
     *
     * @bodyParam ids array required Danh sách ID lịch công tác cần xóa. Example: [1, 2]
     */
    public function bulkDestroy(BulkDestroyScheduleRequest $request)
    {
        $schedules = Schedule::whereIn('id', $request->ids)->get();
        foreach ($schedules as $schedule) {
            $this->authorize('delete', $schedule);
        }

        $this->scheduleService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt lịch công tác thành công!');
    }

    /**
     * Nhập dữ liệu lịch công tác từ Excel.
     *
     * @bodyParam file file required File Excel chứa dữ liệu lịch công tác.
     */
    public function import(ImportScheduleRequest $request)
    {
        $this->authorize('create', Schedule::class);

        $this->scheduleService->import($request->file('file'));

        return $this->success(null, 'Nhập dữ liệu lịch công tác thành công.');
    }

    /**
     * Tải file mẫu nhập dữ liệu lịch công tác.
     */
    public function importTemplate()
    {
        $this->authorize('create', Schedule::class);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(
                \App\Modules\Scheduling\Imports\ScheduleImport::TEMPLATE_LABELS,
                \App\Modules\Scheduling\Imports\ScheduleImport::TEMPLATE_EXAMPLES
            ),
            'import-schedules-template.xlsx'
        );
    }
}
