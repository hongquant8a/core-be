<?php

namespace App\Modules\TaskAssignment\Policies;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskAssignmentItemPolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin / Admin / Quản trị bypass mọi check ownership.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị'])) {
            return true;
        }
        return null;
    }

    /**
     * Xem danh sách — bất kỳ user có quyền index.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-items.index');
    }

    /**
     * Xem chi tiết — bất kỳ user có quyền show.
     */
    public function view(User $user, TaskAssignmentItem $item): bool
    {
        return $user->hasPermissionTo('task-assignment-items.show');
    }

    /**
     * Tạo mới — bất kỳ user có quyền store.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-items.store');
    }

    /**
     * Đổi trạng thái hàng loạt — cần quyền bulkUpdateStatus.
     * Nếu chọn tạm dừng hoặc hủy thì phải là người giao việc của tất cả các task được chọn.
     */
    public function bulkUpdateStatus(User $user): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.bulkUpdateStatus')) {
            return false;
        }

        $status = request('processing_status');
        if (in_array($status, [\App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Paused->value, \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value], true)) {
            $ids = request('ids', []);
            if (! empty($ids)) {
                $invalidCount = TaskAssignmentItem::whereIn('id', $ids)->where('assigned_by', '!=', $user->id)->count();
                if ($invalidCount > 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Xóa hàng loạt — cần quyền bulkDestroy.
     * Người xóa phải là người giao của tất cả các task được chọn.
     */
    public function bulkDestroy(User $user): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.bulkDestroy')) {
            return false;
        }

        $ids = request('ids', []);
        if (! empty($ids)) {
            $invalidCount = TaskAssignmentItem::whereIn('id', $ids)->where('assigned_by', '!=', $user->id)->count();
            if ($invalidCount > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cập nhật — cần có quyền update VÀ là người liên quan đến task:
     *   - Người giao (assigned_by)
     *   - Người được giao (pivot task_assignment_item_user)
     *
     * Trường hợp đặc biệt: Trưởng phòng (có quyền update) không cần check ownership
     * nếu đang quản lý phòng ban — nhưng check đó nằm ở service-level department scope.
     */
    public function update(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.update')) {
            return false;
        }

        if (! $this->checkPauseCancelRestriction($user, $item)) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Xóa — cần có quyền destroy VÀ là người giao.
     */
    public function delete(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.destroy')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Đổi trạng thái (pause/cancel/changeStatus) — người liên quan đến task.
     */
    public function changeStatus(User $user, TaskAssignmentItem $item): bool
    {
        $hasPermission = $user->hasPermissionTo('task-assignment-items.changeStatus')
            || $user->hasPermissionTo('task-assignment-items.pause')
            || $user->hasPermissionTo('task-assignment-items.cancel');

        if (! $hasPermission) {
            return false;
        }

        if (! $this->checkPauseCancelRestriction($user, $item)) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Cập nhật tiến độ — chỉ người được giao hoặc người giao.
     */
    public function updateProgress(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.updateProgress')) {
            return false;
        }

        if (! $this->checkPauseCancelRestriction($user, $item)) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Duyệt hoàn thành (mark-done) — chỉ người giao.
     * Nhân viên thực hiện task không được tự duyệt.
     */
    public function markDone(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.markDone')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Helper: kiểm tra user có liên quan đến task không.
     * - Người giao (assigned_by)
     * - Người được giao (users pivot)
     */
    private function isOwnerOrAssigned(User $user, TaskAssignmentItem $item): bool
    {
        if ((int) $item->assigned_by === $user->id) {
            return true;
        }

        // Kiểm tra trong pivot (tránh N+1: load nếu chưa có)
        $item->loadMissing('users');
        return $item->users->contains('id', $user->id);
    }

    /**
     * Tạm dừng và hủy chỉ cho phép người giao việc (assigner) thực hiện.
     */
    private function checkPauseCancelRestriction(User $user, TaskAssignmentItem $item): bool
    {
        $status = request('processing_status');

        if (in_array($status, [\App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Paused->value, \App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum::Cancelled->value], true)) {
            return (int) $item->assigned_by === $user->id;
        }

        return true;
    }
}
