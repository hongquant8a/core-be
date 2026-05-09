<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Requests\BulkDestroyItemRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusItemRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusItemRequest;
use App\Modules\TaskAssignment\Requests\ExportMonthlyReportRequest;
use App\Modules\TaskAssignment\Requests\StatsFilterRequest;
use App\Modules\TaskAssignment\Requests\StatsByTimeRequest;
use App\Modules\TaskAssignment\Requests\StoreItemRequest;
use App\Modules\TaskAssignment\Requests\UpcomingDeadlineRequest;
use App\Modules\TaskAssignment\Requests\UpdateItemProgressRequest;
use App\Modules\TaskAssignment\Requests\UpdateItemRequest;
use App\Modules\TaskAssignment\Resources\ItemCollection;
use App\Modules\TaskAssignment\Resources\ItemResource;
use App\Modules\TaskAssignment\Resources\TimelineCollection;
use App\Modules\TaskAssignment\Services\TaskAssignmentItemService;
use App\Modules\TaskAssignment\Services\TaskAssignmentTimelineService;

/**
 * @group TaskAssignment - Công việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý công việc: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, xóa hàng loạt và cập nhật tiến độ.
 */
class TaskAssignmentItemController extends Controller
{
    public function __construct(private TaskAssignmentItemService $itemService) {}

    /**
     * Thống kê công việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam processing_status string Lọc theo trạng thái xử lý: todo, in_progress, done, overdue, paused, cancelled. Example: in_progress
     * @queryParam priority string Lọc theo mức độ ưu tiên. Example: high
     * @queryParam deadline_type string Lọc theo loại thời hạn. Example: has_deadline
     * @queryParam task_assignment_document_id integer Lọc theo văn bản giao việc. Example: 1
     * @queryParam task_assignment_item_type_id integer Lọc theo loại công việc. Example: 1
     * @queryParam department_id integer Lọc theo phòng ban được giao. Example: 1
     * @queryParam assignee_id integer Lọc theo người được giao (alias của user_id). Example: 1
     * @queryParam assigner_id integer Lọc theo người giao việc - match assigned_by hoặc created_by (alias của assigned_by_or_created_by). Example: 1
     * @queryParam assignment_role string Vai trò trong công việc: main, support. Dùng kèm assignee_id để phân biệt chủ trì / hỗ trợ. Example: main
     * @queryParam assignment_status string Trạng thái giao việc: assigned, done. Example: assigned
     * @queryParam start_from date Lọc bắt đầu từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam start_to date Lọc bắt đầu đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam end_from date Lọc hạn từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam end_to date Lọc hạn đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": {"total": 18, "todo": 5, "in_progress": 8, "done": 3, "paused": 0, "cancelled": 1, "overdue": 1}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->itemService->stats($request->all()));
    }

    /**
     * Danh sách công việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tên.
     * @queryParam processing_status string Lọc theo trạng thái xử lý: todo, in_progress, done, overdue, paused, cancelled. Example: in_progress
     * @queryParam priority string Lọc theo mức độ ưu tiên. Example: high
     * @queryParam deadline_type string Lọc theo loại thời hạn. Example: has_deadline
     * @queryParam task_assignment_document_id integer Lọc theo văn bản giao việc. Example: 1
     * @queryParam task_assignment_item_type_id integer Lọc theo loại công việc. Example: 1
     * @queryParam department_id integer Lọc theo phòng ban được giao. Example: 1
     * @queryParam assignee_id integer Lọc theo người được giao (alias của user_id). Example: 1
     * @queryParam assigner_id integer Lọc theo người giao việc - match assigned_by hoặc created_by (alias của assigned_by_or_created_by). Example: 1
     * @queryParam assignment_role string Vai trò trong công việc: main, support. Dùng kèm assignee_id để phân biệt chủ trì / hỗ trợ. Example: main
     * @queryParam assignment_status string Trạng thái giao việc: assigned, done. Example: assigned
     * @queryParam start_from date Lọc bắt đầu từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam start_to date Lọc bắt đầu đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam end_from date Lọc hạn từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam end_to date Lọc hạn đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, name, start_at, end_at, completion_percent, priority, created_at, updated_at. Example: end_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\ItemCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->itemService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new ItemCollection($items));
    }

    /**
     * Chi tiết công việc
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentItem $taskAssignmentItem)
    {
        $item = $this->itemService->show($taskAssignmentItem);

        return $this->successResource(new ItemResource($item));
    }

    /**
     * Tạo công việc
     *
     * @bodyParam title string required Tiêu đề công việc. Example: Soạn thảo báo cáo quý I
     * @bodyParam document_id integer required ID văn bản giao việc. Example: 1
     * @bodyParam type_id integer ID loại công việc. Example: 1
     * @bodyParam assignee_ids array Mảng ID người được giao. Example: [1, 2]
     * @bodyParam deadline date Hạn hoàn thành (Y-m-d). Example: 2026-04-30
     * @bodyParam description string Mô tả chi tiết công việc.
     * @bodyParam status string Trạng thái. Example: pending
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true message="Công việc đã được tạo thành công!"
     */
    public function store(StoreItemRequest $request)
    {
        $item = $this->itemService->store($request->validated(), $request->file('attachments', []));

        return $this->successResource(new ItemResource($item), 'Công việc đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật công việc
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @bodyParam title string Tiêu đề công việc.
     * @bodyParam type_id integer ID loại công việc.
     * @bodyParam assignee_ids array Mảng ID người được giao (ghi đè danh sách hiện tại).
     * @bodyParam deadline date Hạn hoàn thành (Y-m-d).
     * @bodyParam description string Mô tả chi tiết công việc.
     * @bodyParam status string Trạng thái.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true message="Công việc đã được cập nhật!"
     */
    public function update(UpdateItemRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $item = $this->itemService->update(
            $taskAssignmentItem,
            $request->validated(),
            $request->file('attachments', []),
            $request->input('remove_attachment_ids', [])
        );

        return $this->successResource(new ItemResource($item), 'Công việc đã được cập nhật!');
    }

    /**
     * Xóa công việc
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Công việc đã được xóa thành công!"}
     */
    public function destroy(TaskAssignmentItem $taskAssignmentItem)
    {
        $this->itemService->destroy($taskAssignmentItem);

        return $this->success(null, 'Công việc đã được xóa thành công!');
    }

    /**
     * Xóa hàng loạt công việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công các công việc được chọn!"}
     */
    public function bulkDestroy(BulkDestroyItemRequest $request)
    {
        $this->itemService->bulkDestroy($request->ids);

        return $this->success(null, 'Đã xóa thành công các công việc được chọn!');
    }

    /**
     * Cập nhật trạng thái hàng loạt công việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam processing_status string required Trạng thái mới: todo, in_progress, done, overdue, paused, cancelled. Example: in_progress
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusItemRequest $request)
    {
        $this->itemService->bulkUpdateStatus($request->ids, $request->processing_status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái công việc
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @bodyParam processing_status string required Trạng thái mới: todo, in_progress, done, overdue, paused, cancelled. Example: done
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true message="Đổi trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusItemRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $item = $this->itemService->changeStatus($taskAssignmentItem, $request->processing_status);

        return $this->successResource(new ItemResource($item), 'Đổi trạng thái thành công!');
    }

    /**
     * Đánh dấu công việc hoàn thành (manager xác nhận).
     *
     * Auto set: processing_status=done, completion_percent=100, completed_at=now().
     * Yêu cầu: task chưa done/cancelled, có ít nhất 1 báo cáo đã được xác nhận và khóa.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true message="Đã đánh dấu hoàn thành."
     *
     * @response 422 {"success": false, "message": "Phải có ít nhất 1 báo cáo đã được xác nhận trước khi đánh dấu hoàn thành."}
     */
    public function markDone(TaskAssignmentItem $taskAssignmentItem)
    {
        try {
            $item = $this->itemService->markDone($taskAssignmentItem);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->successResource(new ItemResource($item), 'Đã đánh dấu hoàn thành.');
    }

    /**
     * Xuất Excel danh sách công việc
     *
     * Xuất ra các trường: id, name, description, document, item_type, deadline_type, start_at, end_at, processing_status, completion_percent, priority, completed_at, departments, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm.
     * @queryParam processing_status string Lọc theo trạng thái xử lý.
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     */
    public function export(FilterRequest $request)
    {
        return $this->itemService->export($request->all());
    }

    /**
     * Xuất báo cáo giao ban tháng (multi-sheet Excel)
     *
     * File Excel gồm nhiều sheet:
     * - Sheet 1: Bảng tổng hợp (phòng ban × trạng thái × loại công việc)
     * - Sheet 2-8: Chi tiết công việc từng phòng ban
     * - Sheet cuối: Chương trình công tác tháng tiếp theo
     *
     * @queryParam month string Tháng báo cáo (Y-m). Mặc định tháng hiện tại nếu bỏ trống. Example: 2026-04
     *
     * @response 200 scenario="File Excel báo cáo giao ban"
     */
    public function exportMonthlyReport(ExportMonthlyReportRequest $request)
    {
        return $this->itemService->exportMonthlyReport($request->month ?? now()->format('Y-m'));
    }

    /**
     * Cập nhật tiến độ công việc
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @bodyParam progress integer required Tiến độ (0-100). Example: 50
     * @bodyParam note string Ghi chú tiến độ.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ItemResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem
     *
     * @apiResourceAdditional success=true message="Cập nhật tiến độ thành công!"
     */
    public function updateProgress(UpdateItemProgressRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $item = $this->itemService->updateProgress($taskAssignmentItem, $request->validated());

        return $this->successResource(new ItemResource($item), 'Cập nhật tiến độ thành công!');
    }

    /**
     * Thống kê công việc theo loại công việc
     *
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     * @queryParam from_date date Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Đến ngày (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": [{"item_type_id": 1, "item_type_name": "TT Thành ủy giao", "total": 19, "todo": 5, "in_progress": 8, "done": 3, "overdue": 2, "paused": 1, "cancelled": 0}]}
     */
    public function statsByItemType(StatsFilterRequest $request)
    {
        return $this->success($this->itemService->statsByItemType($request->all()));
    }

    /**
     * Thống kê công việc theo phòng ban
     *
     * @queryParam processing_status string Lọc theo trạng thái xử lý.
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     * @queryParam deadline_type string Lọc theo loại thời hạn.
     * @queryParam from_date date Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam task_assignment_item_type_id integer Lọc theo loại công việc. Example: 1
     *
     * @response 200 {"success": true, "data": [{"department_id": 1, "department_name": "Phòng Kế toán", "department_code": "KT", "total": 20, "todo": 5, "in_progress": 8, "done": 3, "overdue": 2, "paused": 1, "cancelled": 1}]}
     */
    public function statsByDepartment(StatsFilterRequest $request)
    {
        return $this->success($this->itemService->statsByDepartment($request->all()));
    }

    /**
     * Thống kê công việc theo người dùng
     *
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam processing_status string Lọc theo trạng thái xử lý.
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     * @queryParam from_date date Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Đến ngày (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": [{"user_id": 1, "user_name": "Nguyễn Văn A", "total": 10, "todo": 2, "in_progress": 3, "done": 4, "overdue": 1}]}
     */
    public function statsByUser(StatsFilterRequest $request)
    {
        return $this->success($this->itemService->statsByUser($request->all()));
    }

    /**
     * Thống kê công việc theo văn bản giao việc
     *
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam task_assignment_type_id integer Lọc theo loại văn bản. Example: 1
     * @queryParam from_date date Từ ngày ban hành (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Đến ngày ban hành (Y-m-d). Example: 2026-12-31
     *
     * @response 200 {"success": true, "data": [{"document_id": 1, "document_name": "KH số 123", "issue_date": "2026-03-15", "total_items": 10, "done": 7, "in_progress": 2, "overdue": 1, "completion_rate": 70.0}]}
     */
    public function statsByDocument(StatsFilterRequest $request)
    {
        return $this->success($this->itemService->statsByDocument($request->all()));
    }

    /**
     * Thống kê công việc theo thời gian (tháng)
     *
     * @queryParam from_date date required Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date required Đến ngày (Y-m-d, tối đa 12 tháng). Example: 2026-12-31
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam user_id integer Lọc theo người dùng. Example: 1
     * @queryParam processing_status string Lọc theo trạng thái xử lý.
     *
     * @response 200 {"success": true, "data": [{"month": "2026-01", "total": 15, "done": 8, "overdue": 3, "new_tasks": 10}]}
     */
    public function statsByTime(StatsByTimeRequest $request)
    {
        return $this->success($this->itemService->statsByTime($request->all()));
    }

    /**
     * Danh sách công việc quá hạn
     *
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam user_id integer Lọc theo người dùng. Example: 1
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     * @queryParam from_date string Lọc end_at từ ngày (Y-m-d). Example: 2026-04-01
     * @queryParam to_date string Lọc end_at đến ngày (Y-m-d). Example: 2026-04-30
     * @queryParam sort_by string Sắp xếp theo: end_at, priority, created_at. Example: end_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\ItemCollection
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem paginate=10
     * @apiResourceAdditional success=true
     */
    public function overdue(StatsFilterRequest $request)
    {
        $items = $this->itemService->overdue($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new ItemCollection($items));
    }

    /**
     * Danh sách công việc sắp đến hạn
     *
     * @queryParam days integer Số ngày (1-30, mặc định 3). Example: 3
     * @queryParam department_id integer Lọc theo phòng ban. Example: 1
     * @queryParam user_id integer Lọc theo người dùng. Example: 1
     * @queryParam priority string Lọc theo mức độ ưu tiên.
     * @queryParam from_date string Lọc end_at từ ngày (Y-m-d), AND với khoảng `days`. Example: 2026-04-01
     * @queryParam to_date string Lọc end_at đến ngày (Y-m-d), AND với khoảng `days`. Example: 2026-04-30
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\ItemCollection
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItem paginate=10
     * @apiResourceAdditional success=true
     */
    public function upcomingDeadline(UpcomingDeadlineRequest $request)
    {
        $items = $this->itemService->upcomingDeadline($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new ItemCollection($items));
    }

    /**
     * Timeline công việc
     *
     * Hợp nhất lịch sử trao đổi (notes) và lịch sử chuyển việc (transfers) thành một dòng thời gian duy nhất.
     * Sắp xếp theo thời gian tăng dần (cũ nhất trước).
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 20
     * @queryParam page integer Trang hiện tại. Example: 1
     *
     * @response 200 {"success": true, "data": [{"type": "note", "id": 1, "timestamp": "08:30:00 20/04/2026", "actor": {"id": 3, "name": "Nguyễn Văn A"}, "data": {}}, {"type": "transfer", "id": 1, "timestamp": "14:00:00 21/04/2026", "actor": {"id": 3, "name": "Nguyễn Văn A"}, "data": {}}]}
     */
    public function timeline(FilterRequest $request, TaskAssignmentItem $taskAssignmentItem, TaskAssignmentTimelineService $timelineService)
    {
        $paginator = $timelineService->timeline(
            $taskAssignmentItem->id,
            (int) ($request->limit ?? 20),
            (int) ($request->page ?? 1)
        );

        return $this->successCollection(new TimelineCollection($paginator));
    }
}
