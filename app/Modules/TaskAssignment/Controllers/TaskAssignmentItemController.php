<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Requests\BulkDestroyItemRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusItemRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusItemRequest;
use App\Modules\TaskAssignment\Requests\ImportItemRequest;
use App\Modules\TaskAssignment\Requests\StoreItemRequest;
use App\Modules\TaskAssignment\Requests\UpdateItemProgressRequest;
use App\Modules\TaskAssignment\Requests\UpdateItemRequest;
use App\Modules\TaskAssignment\Resources\ItemCollection;
use App\Modules\TaskAssignment\Resources\ItemResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentItemService;

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
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam document_id integer Lọc theo văn bản giao việc. Example: 1
     * @queryParam assignee_id integer Lọc theo người được giao. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, title, deadline, created_at, updated_at. Example: deadline
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 20, "pending": 5, "in_progress": 10, "completed": 5}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->itemService->stats($request->all()));
    }

    /**
     * Danh sách công việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam document_id integer Lọc theo văn bản giao việc. Example: 1
     * @queryParam assignee_id integer Lọc theo người được giao. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, title, deadline, created_at, updated_at. Example: deadline
     * @queryParam sort_order string Thứ tự: asc, desc. Example: asc
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
        $item = $this->itemService->store($request->validated());

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
        $item = $this->itemService->update($taskAssignmentItem, $request->validated());

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
     * Import danh sách công việc
     *
     * Cột bắt buộc: name. Cột không bắt buộc: description, deadline_type (mặc định no_deadline), start_at, end_at, processing_status (mặc định todo), completion_percent (mặc định 0), priority (mặc định medium).
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     * @bodyParam task_assignment_document_id integer required ID văn bản giao việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Import công việc thành công."}
     */
    public function import(ImportItemRequest $request)
    {
        $this->itemService->import($request->file('file'), (int) $request->task_assignment_document_id);

        return $this->success(null, 'Import công việc thành công.');
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
}
