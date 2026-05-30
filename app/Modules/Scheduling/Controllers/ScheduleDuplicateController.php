<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Requests\DuplicateScheduleRequest;
use App\Modules\Scheduling\Resources\ScheduleResource;
use App\Modules\Scheduling\Services\ScheduleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Sao chép lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Sao chép một lịch công tác sang nhiều ngày khác nhau.
 */
class ScheduleDuplicateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ScheduleService $scheduleService) {}

    /**
     * Sao chép lịch công tác.
     *
     * Nhân bản lịch công tác nguồn sang các ngày đích được chỉ định (tự động sao chép các file đính kèm).
     *
     * @urlParam schedule integer required ID lịch công tác nguồn cần sao chép. Example: 1
     * @bodyParam dates date[] required Danh sách ngày đích cần nhân bản lịch sang (Y-m-d). Example: ["2026-06-08", "2026-06-15"]
     */
    public function duplicate(DuplicateScheduleRequest $request, Schedule $schedule)
    {
        $this->authorize('create', Schedule::class);

        $duplicatedSchedules = $this->scheduleService->duplicate($schedule, $request->input('dates'));

        return $this->success(
            ScheduleResource::collection($duplicatedSchedules),
            'Sao chép lịch công tác thành công!'
        );
    }
}
