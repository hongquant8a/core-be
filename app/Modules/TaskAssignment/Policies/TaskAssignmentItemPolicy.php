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
     * Cập nhật — cần có quyền update VÀ là người liên quan đến task:
     *   - Người tạo (created_by)
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

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Xóa — cần có quyền destroy VÀ là người tạo hoặc người giao.
     */
    public function delete(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.destroy')) {
            return false;
        }

        return $item->created_by === $user->id
            || $item->assigned_by === $user->id;
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

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Cập nhật tiến độ — chỉ người được giao hoặc người tạo.
     */
    public function updateProgress(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.updateProgress')) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Duyệt hoàn thành (mark-done) — chỉ người giao hoặc người tạo.
     * Nhân viên thực hiện task không được tự duyệt.
     */
    public function markDone(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-items.markDone')) {
            return false;
        }

        return $item->created_by === $user->id
            || $item->assigned_by === $user->id;
    }

    /**
     * Helper: kiểm tra user có liên quan đến task không.
     * - Người tạo (created_by)
     * - Người giao (assigned_by)
     * - Người được giao (users pivot)
     */
    private function isOwnerOrAssigned(User $user, TaskAssignmentItem $item): bool
    {
        if ($item->created_by === $user->id || $item->assigned_by === $user->id) {
            return true;
        }

        // Kiểm tra trong pivot (tránh N+1: load nếu chưa có)
        $item->loadMissing('users');
        return $item->users->contains('id', $user->id);
    }
}
