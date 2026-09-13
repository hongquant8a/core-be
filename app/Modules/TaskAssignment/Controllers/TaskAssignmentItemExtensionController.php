<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use App\Modules\TaskAssignment\Requests\ReviewExtensionRequest;
use App\Modules\TaskAssignment\Requests\StoreExtensionRequest;
use App\Modules\TaskAssignment\Resources\ExtensionResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentItemExtensionService;

/**
 * @group TaskAssignment - Gia hạn thời hạn công việc
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Người thực hiện xin dời thời hạn kèm lý do; người đã giao việc duyệt hoặc từ chối.
 * Thời hạn công việc CHỈ đổi khi yêu cầu được duyệt — trong lúc chờ duyệt, công việc
 * quá hạn vẫn tính là quá hạn và lịch nhắc vẫn chạy theo hạn cũ.
 */
class TaskAssignmentItemExtensionController extends Controller
{
    public function __construct(private TaskAssignmentItemExtensionService $extensionService) {}

    /**
     * Lịch sử xin gia hạn của công việc
     *
     * Danh sách các lần xin gia hạn, mới nhất trước, kèm lý do và kết quả duyệt.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": [{"id": 1, "current_end_at": "23:59:59 30/09/2026", "requested_end_at": "23:59:59 15/10/2026", "reason": "Đơn vị phối hợp chưa cung cấp số liệu", "status": "pending", "status_label": "Chờ duyệt"}]}
     */
    public function index(FilterRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $extensions = $this->extensionService->history(
            $taskAssignmentItem->id,
            (int) ($request->limit ?? 10)
        );

        return $this->successCollection(ExtensionResource::collection($extensions));
    }

    /**
     * Gửi yêu cầu gia hạn
     *
     * Người thực hiện gửi yêu cầu dời thời hạn kèm lý do. Thời hạn công việc KHÔNG
     * đổi ở bước này — chỉ đổi khi người giao việc duyệt.
     *
     * Mỗi công việc chỉ có tối đa một yêu cầu đang chờ duyệt.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @response 201 {"success": true, "data": {"id": 1, "status": "pending", "status_label": "Chờ duyệt"}, "message": "Đã gửi yêu cầu gia hạn, đang chờ duyệt."}
     * @response 422 {"success": false, "message": "Công việc đang có một yêu cầu gia hạn chờ duyệt."}
     */
    public function store(StoreExtensionRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        try {
            $extension = $this->extensionService->request($taskAssignmentItem, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->successResource(
            new ExtensionResource($extension->load(['requestedBy', 'reviewedBy'])),
            'Đã gửi yêu cầu gia hạn, đang chờ duyệt.',
            201
        );
    }

    /**
     * Duyệt yêu cầu gia hạn
     *
     * Chỉ người đã giao việc (hoặc quản trị hệ thống) mới duyệt được. Sau khi duyệt,
     * thời hạn công việc dời sang mốc mới và lịch nhắc được dựng lại theo hạn đó.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     * @urlParam extension integer required ID yêu cầu gia hạn. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, "status": "approved", "status_label": "Đã duyệt"}, "message": "Đã duyệt gia hạn, thời hạn công việc đã được cập nhật."}
     * @response 422 {"success": false, "message": "Yêu cầu gia hạn này đã được xử lý."}
     */
    public function approve(ReviewExtensionRequest $request, TaskAssignmentItem $taskAssignmentItem, TaskAssignmentItemExtension $extension)
    {
        try {
            $extension = $this->extensionService->approve($extension, $request->validated()['review_note'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->successResource(
            new ExtensionResource($extension),
            'Đã duyệt gia hạn, thời hạn công việc đã được cập nhật.'
        );
    }

    /**
     * Từ chối yêu cầu gia hạn
     *
     * Thời hạn công việc và lịch nhắc giữ nguyên. Bắt buộc nhập lý do từ chối để
     * người xin biết vì sao và còn xin lại cho đúng.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     * @urlParam extension integer required ID yêu cầu gia hạn. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, "status": "rejected", "status_label": "Bị từ chối"}, "message": "Đã từ chối yêu cầu gia hạn."}
     * @response 422 {"success": false, "message": "Vui lòng nhập lý do từ chối."}
     */
    public function reject(ReviewExtensionRequest $request, TaskAssignmentItem $taskAssignmentItem, TaskAssignmentItemExtension $extension)
    {
        // Ghi chú là `nullable` trong FormRequest vì request này dùng chung cho cả
        // duyệt lẫn từ chối; riêng nhánh từ chối thì bắt buộc.
        $note = trim((string) ($request->validated()['review_note'] ?? ''));
        if ($note === '') {
            return $this->error('Vui lòng nhập lý do từ chối.', 422);
        }

        try {
            $extension = $this->extensionService->reject($extension, $note);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->successResource(new ExtensionResource($extension), 'Đã từ chối yêu cầu gia hạn.');
    }

    /**
     * Thu hồi yêu cầu gia hạn
     *
     * Người gửi tự thu hồi yêu cầu của mình khi còn đang chờ duyệt.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     * @urlParam extension integer required ID yêu cầu gia hạn. Example: 1
     *
     * @response 200 {"success": true, "message": "Đã thu hồi yêu cầu gia hạn."}
     * @response 422 {"success": false, "message": "Chỉ người gửi yêu cầu mới thu hồi được yêu cầu đó."}
     */
    public function destroy(TaskAssignmentItem $taskAssignmentItem, TaskAssignmentItemExtension $extension)
    {
        try {
            $this->extensionService->cancel($extension);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Đã thu hồi yêu cầu gia hạn.');
    }
}
