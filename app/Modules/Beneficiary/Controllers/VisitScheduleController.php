<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Beneficiary\Requests\ChangeStatusVisitScheduleRequest;
use App\Modules\Beneficiary\Resources\VisitScheduleCollection;
use App\Modules\Beneficiary\Resources\VisitScheduleResource;
use App\Modules\Beneficiary\Services\VisitScheduleService;
use App\Modules\Core\Requests\FilterRequest;

/**
 * @group Beneficiary - Lịch viếng thăm
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Lịch viếng thăm/tặng quà — sinh tự động bởi Console Command (Tết, 27/7) hoặc bởi cán bộ
 * tạo lịch nhắc thủ công qua Tinker/Seeder cho occasion=custom. Chỉ có index/show/changeStatus,
 * không có store/destroy qua API — xem lý do ở kế hoạch triển khai. Nhắc trước N ngày dùng lại
 * hạ tầng Reminder chung (xem /beneficiary/notification-config/event-configs).
 */
class VisitScheduleController extends Controller
{
    public function __construct(private VisitScheduleService $visitScheduleService) {}

    /**
     * Danh sách lịch viếng thăm
     *
     * @queryParam assigned_to integer Lọc theo cán bộ phụ trách.
     * @queryParam status string Lọc theo trạng thái: pending, done, skipped.
     * @queryParam from_date date Lọc từ ngày viếng thăm (Y-m-d).
     * @queryParam to_date date Lọc đến ngày viếng thăm (Y-m-d).
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\Beneficiary\Resources\VisitScheduleCollection
     * @apiResourceModel App\Modules\Beneficiary\Models\VisitSchedule paginate=10
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->visitScheduleService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new VisitScheduleCollection($items));
    }

    /**
     * Chi tiết lịch viếng thăm
     *
     * @urlParam visitSchedule integer required ID lịch viếng thăm. Example: 1
     *
     * @apiResource App\Modules\Beneficiary\Resources\VisitScheduleResource
     * @apiResourceModel App\Modules\Beneficiary\Models\VisitSchedule
     * @apiResourceAdditional success=true
     */
    public function show(VisitSchedule $visitSchedule)
    {
        return $this->successResource(new VisitScheduleResource($this->visitScheduleService->show($visitSchedule)));
    }

    /**
     * Đổi trạng thái lịch viếng thăm (done/skipped)
     *
     * done kèm ảnh xác nhận qua MediaService (collection visit_evidence). skipped kèm ghi chú lý do.
     *
     * @urlParam visitSchedule integer required ID lịch viếng thăm. Example: 1
     * @bodyParam status string required done hoặc skipped. Example: done
     * @bodyParam note string Ghi chú.
     * @bodyParam evidence file[] Ảnh xác nhận (chỉ áp dụng khi status = done).
     *
     * @apiResource App\Modules\Beneficiary\Resources\VisitScheduleResource
     * @apiResourceModel App\Modules\Beneficiary\Models\VisitSchedule
     * @apiResourceAdditional success=true message="Cập nhật lịch viếng thăm thành công!"
     */
    public function changeStatus(ChangeStatusVisitScheduleRequest $request, VisitSchedule $visitSchedule)
    {
        $item = $this->visitScheduleService->changeStatus(
            $visitSchedule,
            $request->status,
            $request->input('note'),
            $request->file('evidence', []),
        );

        return $this->successResource(new VisitScheduleResource($item), 'Cập nhật lịch viếng thăm thành công!');
    }
}
