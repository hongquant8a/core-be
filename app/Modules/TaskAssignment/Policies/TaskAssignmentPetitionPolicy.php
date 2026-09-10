<?php

namespace App\Modules\TaskAssignment\Policies;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Phân quyền đơn thư theo hai trục tách bạch:
 *  - QUYỀN  (`task-assignment-petitions.*`) quyết định ĐƯỢC LÀM GÌ.
 *  - PHẠM VI quyết định LÀM TRÊN ĐƠN NÀO: có `viewAll` thì mọi phòng ban,
 *    không có thì chỉ đơn thuộc phòng ban mình là thành viên.
 *
 * Trước đây bốn thao tác destroy/bulkDestroy/bulkUpdateStatus/manage còn AND
 * ngầm với cờ `is_petition_overview` của phòng ban, nên cấp quyền ở màn Vai trò
 * mà người dùng vẫn nhận 403. Cờ đó đã bỏ — phạm vi nay do quyền quyết định.
 */
class TaskAssignmentPetitionPolicy
{
    use HandlesAuthorization;

    /** Ai có `task-overview.manageAll` thì bypass mọi kiểm tra phạm vi. */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('task-overview.manageAll')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('task-assignment-petitions.index');
    }

    public function view(User $user, TaskAssignmentPetition $petition): bool
    {
        return $user->can('task-assignment-petitions.show')
            && $this->inScope($user, $petition);
    }

    public function create(User $user): bool
    {
        return $user->can('task-assignment-petitions.store');
    }

    public function update(User $user, TaskAssignmentPetition $petition): bool
    {
        return ! $this->isCompleted($petition)
            && $user->can('task-assignment-petitions.update')
            && $this->inScope($user, $petition);
    }

    /**
     * Đơn đã hoàn thành cũng khoá xoá, cùng một khoá với sửa và đổi trạng thái.
     *
     * Trước đây chỉ `update`/`changeStatus` xét khoá, còn xoá thì không — nên
     * sửa một chữ trong đơn đã chốt thì bị chặn, mà xoá sạch cả đơn lại được.
     * Bảng `task_assignment_petitions` không có `deleted_at`: xoá là xoá vĩnh
     * viễn, hồ sơ kiến nghị của người dân biến mất không khôi phục được.
     * Muốn xoá thật thì mở khoá trước (`unlock`, cần quyền `manage`).
     */
    public function delete(User $user, TaskAssignmentPetition $petition): bool
    {
        return ! $this->isCompleted($petition)
            && $user->can('task-assignment-petitions.destroy')
            && $this->inScope($user, $petition);
    }

    /** Bulk: kiểm quyền ở đây, phạm vi và khoá từng dòng do service lọc. */
    public function bulkDestroy(User $user): bool
    {
        return $user->can('task-assignment-petitions.bulkDestroy');
    }

    public function bulkUpdateStatus(User $user): bool
    {
        return $user->can('task-assignment-petitions.bulkUpdateStatus');
    }

    public function changeStatus(User $user, TaskAssignmentPetition $petition): bool
    {
        return ! $this->isCompleted($petition)
            && $user->can('task-assignment-petitions.changeStatus')
            && $this->inScope($user, $petition);
    }

    /** Mở khóa đơn đã hoàn thành để sửa lại. */
    public function unlock(User $user, TaskAssignmentPetition $petition): bool
    {
        return $user->can('task-assignment-petitions.manage')
            && $this->inScope($user, $petition);
    }

    private function isCompleted(TaskAssignmentPetition $petition): bool
    {
        return $petition->processing_status === PetitionStatusEnum::Completed->value;
    }

    /**
     * Đơn thư có nằm trong phạm vi thao tác của user không.
     *
     * `viewAll` = mọi phòng ban. Không có thì phải là thành viên đang hoạt động
     * của chính phòng ban tiếp nhận đơn.
     */
    private function inScope(User $user, TaskAssignmentPetition $petition): bool
    {
        if ($user->can('task-assignment-petitions.viewAll')) {
            return true;
        }

        if (! $petition->department_id) {
            return false;
        }

        return TaskAssignmentEmployeeDepartment::forUser($user->id)
            ->activeEmployee()
            ->where('task_assignment_department_id', $petition->department_id)
            ->exists();
    }
}
