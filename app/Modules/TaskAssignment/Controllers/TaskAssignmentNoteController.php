<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Requests\StoreNoteRequest;
use App\Modules\TaskAssignment\Resources\NoteResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentNoteService;

/**
 * @group TaskAssignment - Ghi chú / Trao đổi
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý ghi chú trao đổi giữa quản lý và người thực hiện theo từng công việc (append-only).
 */
class TaskAssignmentNoteController extends Controller
{
    public function __construct(private TaskAssignmentNoteService $noteService) {}

    /**
     * Thêm ghi chú / trao đổi
     *
     * Thêm một dòng nhận xét/trao đổi vào timeline công việc.
     * Append-only: không thể sửa/xóa nội dung đã gửi.
     * author_role tự động xác định theo quyền người đăng nhập.
     *
     * @urlParam taskAssignmentItem integer required ID công việc. Example: 1
     *
     * @bodyParam content string required Nội dung ghi chú (tối đa 5000 ký tự). Example: Yêu cầu hoàn thành trước ngày 25/04
     *
     * @response 201 {"success": true, "data": {"id": 1, "author": {"id": 3, "name": "Nguyễn Văn A"}, "author_role": "manager", "content": "Yêu cầu hoàn thành trước ngày 25/04", "created_at": "08:30:00 22/04/2026"}, "message": "Đã thêm ghi chú!"}
     */
    public function store(StoreNoteRequest $request, TaskAssignmentItem $taskAssignmentItem)
    {
        $note = $this->noteService->store($taskAssignmentItem, $request->validated());

        return $this->successResource(new NoteResource($note), 'Đã thêm ghi chú!', 201);
    }
}
