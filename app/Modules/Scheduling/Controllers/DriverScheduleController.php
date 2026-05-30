<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Resources\DriverScheduleResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - View dành cho Lái xe
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * API dành riêng cho Lái xe để tra cứu lịch trình được phân công.
 */
class DriverScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Danh sách lịch trình của lái xe.
     *
     * Lấy danh sách lịch trình đã được công bố mà lái xe hiện tại được phân công phục vụ.
     *
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-06-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-06-07
     * @queryParam from date Alternative lọc từ ngày (Y-m-d). Example: 2026-06-01
     * @queryParam to date Alternative lọc đến ngày (Y-m-d). Example: 2026-06-07
     */
    public function index(FilterRequest $request)
    {
        $user = auth()->user();

        // Ensure user is driver or has authority
        if (!$user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        $fromDate = $request->input('from_date') ?? $request->input('from') ?? now()->startOfWeek()->format('Y-m-d');
        $toDate = $request->input('to_date') ?? $request->input('to') ?? now()->endOfWeek()->format('Y-m-d');

        $query = Schedule::with(['host', 'driver'])
            ->where('status', ScheduleStatusEnum::Published);

        // Drivers only see their own assigned schedules. Other roles can see schedules of the driver specified (or if none, all)
        if ($user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            $query->where('driver_id', $user->id);
        } elseif ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        $schedules = $query->whereBetween('event_date', [$fromDate, $toDate])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return $this->success(DriverScheduleResource::collection($schedules));
    }

    /**
     * Chi tiết lịch của lái xe.
     *
     * Xem chi tiết thông tin rút gọn của một lịch trình cụ thể mà lái xe được phân công.
     *
     * @urlParam id integer required ID lịch công tác. Example: 1
     */
    public function show(int $id)
    {
        $user = auth()->user();

        $schedule = Schedule::with(['host', 'driver'])->findOrFail($id);

        // Ensure user is allowed to see the schedule
        if ($user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            if ((int) $schedule->driver_id !== (int) $user->id) {
                abort(403, 'Bạn không được phân công lịch này.');
            }
        }

        // Must be published
        if ($schedule->status !== ScheduleStatusEnum::Published) {
            abort(404, 'Không tìm thấy lịch công tác đã công bố.');
        }

        return $this->successResource(new DriverScheduleResource($schedule));
    }
}
