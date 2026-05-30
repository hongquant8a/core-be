<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Requests\ReorderScheduleRequest;
use App\Modules\Scheduling\Services\ScheduleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Sắp xếp lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Thay đổi thứ tự sắp xếp thủ công (sort_order) cho danh sách lịch công tác.
 */
class ScheduleReorderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ScheduleService $scheduleService) {}

    /**
     * Sắp xếp lại lịch công tác.
     *
     * Cập nhật hàng loạt trường sort_order của các bản ghi lịch công tác được truyền lên.
     *
     * @bodyParam orders object[] required Danh sách sắp xếp. Example: [{"id": 1, "sort_order": 1}, {"id": 2, "sort_order": 2}]
     * @bodyParam orders[].id integer required ID lịch công tác. Example: 1
     * @bodyParam orders[].sort_order integer required Thứ tự sắp xếp mới. Example: 1
     */
    public function reorder(ReorderScheduleRequest $request)
    {
        $this->authorize('reorder', Schedule::class);

        $this->scheduleService->reorder($request->input('orders'));

        return $this->success(null, 'Sắp xếp lịch công tác thành công!');
    }
}
