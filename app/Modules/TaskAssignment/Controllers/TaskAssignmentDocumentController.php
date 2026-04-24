<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Requests\BulkDestroyDocumentRequest;
use App\Modules\TaskAssignment\Requests\BulkUpdateStatusDocumentRequest;
use App\Modules\TaskAssignment\Requests\ChangeDocumentStatusRequest;
use App\Modules\TaskAssignment\Requests\DocumentStatsByTimeRequest;
use App\Modules\TaskAssignment\Requests\StoreDocumentRequest;
use App\Modules\TaskAssignment\Requests\UpdateDocumentRequest;
use App\Modules\TaskAssignment\Resources\DocumentCollection;
use App\Modules\TaskAssignment\Resources\DocumentResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentDocumentService;

/**
 * @group TaskAssignment - Văn bản giao việc
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý văn bản giao việc: thống kê, danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt, xuất/nhập và đổi trạng thái.
 */
class TaskAssignmentDocumentController extends Controller
{
    public function __construct(private TaskAssignmentDocumentService $documentService) {}

    /**
     * Thống kê văn bản giao việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề, số ký hiệu.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam type_id integer Lọc theo loại văn bản. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, title, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 10, "active": 8, "inactive": 2}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->documentService->stats($request->all()));
    }

    /**
     * Thống kê văn bản giao việc theo thời gian (tháng)
     *
     * @queryParam from_date date required Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date required Đến ngày (Y-m-d, tối đa 12 tháng). Example: 2026-12-31
     * @queryParam task_assignment_type_id integer Lọc theo loại văn bản. Example: 1
     *
     * @response 200 {"success": true, "data": [{"month": "2026-01", "total": 5, "draft": 1, "issued": 4}]}
     */
    public function statsByTime(DocumentStatsByTimeRequest $request)
    {
        return $this->success($this->documentService->statsByTime($request->all()));
    }

    /**
     * Danh sách văn bản giao việc
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề, số ký hiệu.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam type_id integer Lọc theo loại văn bản. Example: 1
     * @queryParam from_date date Lọc từ ngày tạo (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày tạo (Y-m-d). Example: 2026-12-31
     * @queryParam sort_by string Sắp xếp theo: id, title, created_at, updated_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\TaskAssignment\Resources\DocumentCollection
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDocument paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $items = $this->documentService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new DocumentCollection($items));
    }

    /**
     * Chi tiết văn bản giao việc
     *
     * @urlParam taskAssignmentDocument integer required ID văn bản giao việc. Example: 1
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DocumentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDocument
     *
     * @apiResourceAdditional success=true
     */
    public function show(TaskAssignmentDocument $taskAssignmentDocument)
    {
        $doc = $this->documentService->show($taskAssignmentDocument);

        return $this->successResource(new DocumentResource($doc));
    }

    /**
     * Tạo văn bản giao việc
     *
     * @bodyParam title string required Tiêu đề văn bản. Example: Quyết định giao việc tháng 4
     * @bodyParam type_id integer required ID loại văn bản. Example: 1
     * @bodyParam status string required Trạng thái. Example: draft
     * @bodyParam attachments[] file Tệp đính kèm (tối đa 10 tệp, multipart/form-data).
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DocumentResource status=201
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDocument
     *
     * @apiResourceAdditional success=true message="Văn bản giao việc đã được tạo thành công!"
     */
    public function store(StoreDocumentRequest $request)
    {
        $doc = $this->documentService->store($request->validated(), $request->file('attachments', []));

        return $this->successResource(new DocumentResource($doc), 'Văn bản giao việc đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật văn bản giao việc
     *
     * @urlParam taskAssignmentDocument integer required ID văn bản giao việc. Example: 1
     *
     * @bodyParam title string Tiêu đề văn bản.
     * @bodyParam type_id integer ID loại văn bản.
     * @bodyParam status string Trạng thái.
     * @bodyParam attachments[] file Tệp đính kèm mới (append, multipart/form-data).
     * @bodyParam remove_attachment_ids array Mảng ID đính kèm cần xóa.
     *
     * **Xử lý file đính kèm:**
     * - `multipart/form-data` + `attachments[]` → upload file mới, thêm vào danh sách đính kèm.
     * - `remove_attachment_ids` → xóa file đính kèm theo ID.
     * - Không gửi `attachments[]` và `remove_attachment_ids` → giữ nguyên.
     * - Không thể chỉnh sửa văn bản đã ban hành (status=issued). Phải chuyển về draft trước.
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DocumentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDocument
     *
     * @apiResourceAdditional success=true message="Văn bản giao việc đã được cập nhật!"
     */
    public function update(UpdateDocumentRequest $request, TaskAssignmentDocument $taskAssignmentDocument)
    {
        $doc = $this->documentService->update(
            $taskAssignmentDocument,
            $request->validated(),
            $request->file('attachments', []),
            $request->input('remove_attachment_ids', [])
        );

        return $this->successResource(new DocumentResource($doc), 'Văn bản giao việc đã được cập nhật!');
    }

    /**
     * Xóa văn bản giao việc
     *
     * @urlParam taskAssignmentDocument integer required ID văn bản giao việc. Example: 1
     *
     * @response 200 {"success": true, "message": "Văn bản giao việc đã được xóa thành công!"}
     */
    public function destroy(TaskAssignmentDocument $taskAssignmentDocument)
    {
        $this->documentService->destroy($taskAssignmentDocument);

        return $this->success(null, 'Văn bản giao việc đã được xóa thành công!');
    }

    /**
     * Xóa hàng loạt văn bản giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công các văn bản giao việc được chọn!"}
     */
    public function bulkDestroy(BulkDestroyDocumentRequest $request)
    {
        $this->documentService->bulkDestroy($request->ids);

        return $this->success(null, 'Đã xóa thành công các văn bản giao việc được chọn!');
    }

    /**
     * Cập nhật trạng thái hàng loạt văn bản giao việc
     *
     * @bodyParam ids array required Danh sách ID. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới: draft, issued. Example: draft
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái hàng loạt thành công!"}
     */
    public function bulkUpdateStatus(BulkUpdateStatusDocumentRequest $request)
    {
        $this->documentService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái văn bản giao việc
     *
     * @urlParam taskAssignmentDocument integer required ID văn bản giao việc. Example: 1
     *
     * @bodyParam status string required Trạng thái mới. Example: published
     *
     * @apiResource App\Modules\TaskAssignment\Resources\DocumentResource
     *
     * @apiResourceModel App\Modules\TaskAssignment\Models\TaskAssignmentDocument
     *
     * @apiResourceAdditional success=true message="Cập nhật trạng thái thành công!"
     */
    public function changeStatus(ChangeDocumentStatusRequest $request, TaskAssignmentDocument $taskAssignmentDocument)
    {
        $doc = $this->documentService->changeStatus($taskAssignmentDocument, $request->status);

        return $this->successResource(new DocumentResource($doc), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Xuất Excel văn bản giao việc
     *
     * Áp dụng cùng bộ lọc với index. Xuất ra các trường: id, title, status, type, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề.
     * @queryParam status string Lọc theo trạng thái.
     * @queryParam type_id integer Lọc theo loại văn bản.
     * @queryParam sort_by string Sắp xếp theo: id, title, created_at.
     * @queryParam sort_order string Thứ tự: asc, desc.
     */
    public function export(FilterRequest $request)
    {
        return $this->documentService->export($request->all());
    }

}
