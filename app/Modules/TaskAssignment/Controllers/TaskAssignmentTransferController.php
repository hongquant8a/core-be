<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Requests\StoreTransferRequest;
use App\Modules\TaskAssignment\Resources\TransferResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentTransferService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group TaskAssignment - Chuyển công việc
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý chuyển công việc giữa người thực hiện: tạo điều chuyển và xem lịch sử.
 */
class TaskAssignmentTransferController extends Controller
{
    public function __construct(private TaskAssignmentTransferService $transferService) {}

    /**
     * Chuyển công việc cho người dùng khác
     *
     * Chuyển người thực hiện hiện tại (hoặc assignee main nếu manager chuyển hộ) sang user mới.
     * Record cũ chuyển assignment_status='transferred', record mới tạo với assignment_status='assigned'.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @bodyParam to_user_id integer required ID người nhận công việc mới. Example: 5
     * @bodyParam note string Ghi chú lý do chuyển. Example: Chuyển do nghỉ phép
     *
     * @response 201 {"success": true, "data": {"id": 1, "from_user": {"id": 3, "name": "Nguyễn Văn A"}, "to_user": {"id": 5, "name": "Trần Thị B"}, "transferred_at": "14:00:00 22/04/2026", "note": "Chuyển do nghỉ phép"}, "message": "Đã chuyển công việc thành công!"}
     * @response 422 {"success": false, "message": "Công việc đã đóng, không thể chuyển."}
     */
    public function store(StoreTransferRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        try {
            $transfer = $this->transferService->transfer($taskAssignmentItem, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->successResource(new TransferResource($transfer), 'Đã chuyển công việc thành công!', 201);
    }

    /**
     * Lịch sử chuyển công việc
     *
     * Danh sách các lần chuyển người thực hiện của công việc, sắp xếp mới nhất trước.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": [{"id": 1, "from_user": {"id": 3, "name": "Nguyễn Văn A"}, "to_user": {"id": 5, "name": "Trần Thị B"}, "transferred_at": "14:00:00 22/04/2026"}]}
     */
    public function index(FilterRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $transfers = $this->transferService->history(
            $taskAssignmentItem->id,
            (int) ($request->limit ?? 10)
        );

        return $this->successCollection(TransferResource::collection($transfers));
    }
}
