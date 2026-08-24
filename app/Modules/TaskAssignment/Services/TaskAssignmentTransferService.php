<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemUserTransfer;
use Illuminate\Support\Facades\DB;

class TaskAssignmentTransferService
{
    /**
     * Chuyển công việc từ user hiện tại (hoặc user chỉ định) sang user khác.
     *
     * Logic spec §8.2 Luồng B step 5-6:
     * - Update record cũ: assignment_status = 'transferred'
     * - Tạo record mới cho to_user: assignment_status = 'assigned'
     * - Lưu lịch sử tại task_assignment_item_user_transfers
     *
     * @throws \RuntimeException
     */
    public function transfer(TaskAssignmentItem $item, array $validated): TaskAssignmentItemUserTransfer
    {
        $toUserId = $validated['to_user_id'];
        $note = $validated['note'] ?? null;
        $currentUserId = auth()->id();

        // Validate: task chưa done/cancelled
        if (in_array($item->processing_status, [
            TaskProgressStatusEnum::Done->value,
            TaskProgressStatusEnum::Cancelled->value,
        ], true)) {
            throw new \RuntimeException('Công việc đã đóng, không thể chuyển.');
        }

        // Tìm record phân công hiện tại có thể chuyển
        // Ưu tiên: current user là assignee → chuyển từ mình
        // Nếu current user là manager (không phải assignee) → tìm assignee main
        $fromPivot = DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->where('user_id', $currentUserId)
            ->whereIn('assignment_status', [TaskUserAssignmentStatusEnum::Assigned->value, TaskUserAssignmentStatusEnum::Done->value])
            ->first();

        if (! $fromPivot) {
            // Current user không phải assignee → tìm assignee main (manager chuyển hộ)
            $fromPivot = DB::table('task_assignment_item_user')
                ->where('task_assignment_item_id', $item->id)
                ->whereIn('assignment_status', [TaskUserAssignmentStatusEnum::Assigned->value, TaskUserAssignmentStatusEnum::Done->value])
                ->where('assignment_role', 'main')
                ->first();
        }

        if (! $fromPivot) {
            throw new \RuntimeException('Không tìm thấy người thực hiện hiện tại để chuyển.');
        }

        $fromUserId = $fromPivot->user_id;

        // Validate: không chuyển cho chính mình
        if ((int) $toUserId === (int) $fromUserId) {
            throw new \RuntimeException('Không thể chuyển công việc cho chính người đang thực hiện.');
        }

        // Validate: to_user chưa là assignee active
        $existingAssignment = DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->where('user_id', $toUserId)
            ->whereIn('assignment_status', [TaskUserAssignmentStatusEnum::Assigned->value, TaskUserAssignmentStatusEnum::Done->value])
            ->exists();

        if ($existingAssignment) {
            throw new \RuntimeException('Người nhận đã được giao công việc này.');
        }

        // Phòng ban ghi vào dòng phân công mới:
        //  - FE gửi `to_department_id` khi người nhận thuộc nhiều phòng (request bắt buộc lúc đó);
        //  - không gửi thì lấy phòng duy nhất của người nhận;
        //  - người nhận chưa thuộc phòng nào thì giữ phòng của dòng phân công cũ.
        $toDepartmentId = $validated['to_department_id']
            ?? TaskAssignmentEmployeeDepartment::forUser($toUserId)
                ->activeEmployee()
                ->value('task_assignment_department_id')
            ?? $fromPivot->department_id;

        return DB::transaction(function () use ($item, $fromPivot, $fromUserId, $toUserId, $toDepartmentId, $note, $currentUserId) {
            // 1. Xóa record cũ (không giữ lại record mang status transferred trong bảng pivot)
            DB::table('task_assignment_item_user')
                ->where('id', $fromPivot->id)
                ->delete();

            // 2. Tạo record mới cho to_user
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'user_id' => $toUserId,
                'department_id' => $toDepartmentId,
                'department_role' => $fromPivot->department_role,
                'assignment_role' => $fromPivot->assignment_role,
                'assignment_status' => TaskUserAssignmentStatusEnum::Assigned->value,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Lưu lịch sử transfer
            $transfer = TaskAssignmentItemUserTransfer::create([
                'task_assignment_item_id' => $item->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'transferred_by_user_id' => $currentUserId,
                'transferred_department_id' => $fromPivot->department_id,
                'transferred_at' => now(),
                'note' => $note,
                'organization_id' => $item->organization_id,
            ]);

            // 4. Fire TaskAssigned cho to_user nếu document đã issued (assignee mới cần biết)
            $item->loadMissing('document');
            if ($item->document?->status === \App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum::Issued->value) {
                $toUser = \App\Modules\Core\Models\User::find($toUserId);
                if ($toUser) {
                    event(new \App\Services\Notification\Events\TaskAssigned($item, $toUser));
                }
            }

            return $transfer->load(['fromUser', 'toUser', 'transferredBy', 'department']);
        });
    }

    /**
     * Lịch sử chuyển việc theo công việc.
     */
    public function history(int $itemId, int $limit)
    {
        return TaskAssignmentItemUserTransfer::where('task_assignment_item_id', $itemId)
            ->with(['fromUser', 'toUser', 'transferredBy', 'department'])
            ->orderBy('transferred_at', 'desc')
            ->paginate($limit);
    }
}
