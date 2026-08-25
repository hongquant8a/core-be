<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Requests\StoreReportRequest;
use App\Modules\TaskAssignment\Requests\UpdateReportRequest;
use App\Modules\TaskAssignment\Resources\ReportCollection;
use App\Modules\TaskAssignment\Resources\ReportResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentReportService;
use Illuminate\Support\Facades\Gate;

/**
 * @group TaskAssignment - Báo cáo công việc
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý báo cáo công việc: danh sách, tạo, cập nhật, xóa báo cáo theo từng công việc.
 */
class TaskAssignmentItemReportController extends Controller
{
    public function __construct(private TaskAssignmentReportService $reportService) {}

    /**
     * Danh sách báo cáo công việc
     *
     * @queryParam task_assignment_item_id integer required ID công việc. Example: 1
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\ReportCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemReport paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $request->validate([
            'task_assignment_item_id' => 'required|integer|exists:task_assignment_items,id',
        ]);

        // Không gác thì ai có quyền báo cáo cũng đọc được báo cáo của công việc
        // bất kỳ, chỉ cần đoán id. Dùng withoutGlobalScope giống store: công việc
        // thuộc văn bản chưa ban hành vẫn phải tra ra được để từ chối cho đúng.
        $item = TaskAssignmentItem::withoutGlobalScope('issuedDocument')
            ->findOrFail((int) $request->input('task_assignment_item_id'));

        Gate::authorize('viewReports', $item);

        $reports = $this->reportService->index(
            (int) $request->input('task_assignment_item_id'),
            (int) ($request->limit ?? 10)
        );

        return $this->successCollection(new ReportCollection($reports));
    }

    /**
     * Chi tiết báo cáo công việc
     *
     * @urlParam taskAssignmentItemReport integer required ID báo cáo. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ReportResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemReport
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentItemReport $taskAssignmentItemReport)
    {
        $report = $taskAssignmentItemReport->load(['reporter', 'attachments.media']);

        return $this->successResource(new ReportResource($report));
    }

    /**
     * Tạo báo cáo công việc
     *
     * @bodyParam task_assignment_item_id integer required ID công việc. Example: 1
     * @bodyParam completion_percent integer Tiến độ hoàn thành (0-100). Nếu đạt 100%, công việc sẽ chuyển sang trạng thái chờ duyệt. Example: 100
     * @bodyParam completed_at date Ngày hoàn thành công việc (Y-m-d). Example: 2026-04-30
     * @bodyParam report_document_number string Số hiệu văn bản báo cáo. Example: BC-01/2026
     * @bodyParam report_document_excerpt string Trích yếu nội dung báo cáo. Example: Báo cáo kết quả thực hiện công việc tháng 4/2026
     * @bodyParam report_document_content string Nội dung chi tiết báo cáo.
     * @bodyParam attachments[] file Tệp đính kèm (tối đa 10 tệp, multipart/form-data).
     *
     * **Xử lý file đính kèm:** gửi `multipart/form-data` với `attachments[]` để upload.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ReportResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemReport
     *
     * @apiResourceAdditional success=true message="Báo cáo đã được tạo thành công!"
     */
    public function store(StoreReportRequest $request)
    {
        $item = TaskAssignmentItem::withoutGlobalScope('issuedDocument')->findOrFail($request->input('task_assignment_item_id'));

        Gate::authorize('report', $item);

        $report = $this->reportService->store($item, $request->validated(), $request->file('attachments', []));

        return $this->successResource(new ReportResource($report), 'Báo cáo đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật báo cáo công việc
     *
     * @urlParam taskAssignmentItemReport integer required ID báo cáo. Example: 1
     *
     * @bodyParam content string Nội dung báo cáo.
     * @bodyParam progress integer Tiến độ tại thời điểm báo cáo (0-100).
     * @bodyParam attachments[] file Tệp đính kèm mới (append, multipart/form-data).
     * @bodyParam remove_attachment_ids array Mảng ID đính kèm cần xóa.
     *
     * **Xử lý file đính kèm:**
     * - `attachments[]` → upload file mới, thêm vào danh sách.
     * - `remove_attachment_ids` → xóa file theo ID.
     * - Không gửi → giữ nguyên.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\ReportResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentItemReport
     *
     * @apiResourceAdditional success=true message="Báo cáo đã được cập nhật!"
     */
    public function update(UpdateReportRequest $request, TaskAssignmentItemReport $taskAssignmentItemReport)
    {
        $report = $this->reportService->update(
            $taskAssignmentItemReport,
            $request->validated(),
            $request->file('attachments', []),
            $request->input('remove_attachment_ids', [])
        );

        return $this->successResource(new ReportResource($report), 'Báo cáo đã được cập nhật!');
    }

    /**
     * Xóa báo cáo công việc
     *
     * @urlParam taskAssignmentItemReport integer required ID báo cáo. Example: 1
     *
     * @response 200 {"success": true, "message": "Báo cáo đã được xóa thành công!"}
     */
    public function destroy(TaskAssignmentItemReport $taskAssignmentItemReport)
    {
        $this->reportService->destroy($taskAssignmentItemReport);

        return $this->success(null, 'Báo cáo đã được xóa thành công!');
    }
}
