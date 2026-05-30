<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Resources\ScheduleResource;
use App\Modules\Scheduling\Services\ScheduleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Duyệt lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Phê duyệt hoặc từ chối lịch công tác tuần/ngày theo cấu hình tổ chức.
 */
class ScheduleApprovalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ScheduleService $scheduleService) {}

    /**
     * Phê duyệt lịch công tác.
     *
     * Chuyển trạng thái lịch từ Chờ duyệt (Pending) sang Đã duyệt/Công bố (Published).
     *
     * @urlParam schedule integer required ID lịch công tác cần duyệt. Example: 1
     */
    public function approve(Schedule $schedule)
    {
        $this->authorize('approve', $schedule);

        $approvedSchedule = $this->scheduleService->approve($schedule);

        return $this->successResource(new ScheduleResource($approvedSchedule), 'Duyệt lịch công tác thành công!');
    }

    /**
     * Từ chối duyệt lịch công tác.
     *
     * Chuyển trạng thái lịch về Bản nháp (Draft) từ trạng thái Chờ duyệt.
     *
     * @urlParam schedule integer required ID lịch công tác cần từ chối duyệt. Example: 1
     */
    public function reject(Schedule $schedule)
    {
        $this->authorize('approve', $schedule);

        $rejectedSchedule = $this->scheduleService->reject($schedule);

        return $this->successResource(new ScheduleResource($rejectedSchedule), 'Từ chối duyệt lịch công tác thành công!');
    }
}
