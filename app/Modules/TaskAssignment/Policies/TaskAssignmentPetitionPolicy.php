<?php

namespace App\Modules\TaskAssignment\Policies;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskAssignmentPetitionPolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin / Admin / Quản trị bypass mọi check ownership.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Super Admin'])) {
            return true;
        }
        return null;
    }

    /**
     * Xem danh sách đơn thư — bất kỳ user có quyền index.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-petitions.index');
    }

    /**
     * Xem chi tiết đơn thư — bất kỳ user có quyền show VÀ là người có đơn thư này ở phòng ban của mình (nếu không phải là người tạo).
     */
    public function view(User $user, TaskAssignmentPetition $petition): bool
    {
        if (! $user->hasPermissionTo('task-assignment-petitions.show')) {
            return false;
        }



        if ($this->isUserInOverviewDepartment($user)) {
            return true;
        }

        // Người trong phòng ban được giao của đơn thư cũng có quyền xem.
        if ($petition->department_id) {
            return TaskAssignmentUser::where('user_id', $user->id)
                ->where('task_assignment_department_id', $petition->department_id)
                ->exists();
        }

        return false;
    }

    /**
     * Tạo mới đơn thư — bất kỳ user có quyền store.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-petitions.store');
    }

    /**
     * Cập nhật đơn thư — cần có quyền update VÀ là người tạo hoặc trong phòng ban được giao.
     */
    public function update(User $user, TaskAssignmentPetition $petition): bool
    {
        if ($petition->processing_status === \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Completed->value) {
            return false;
        }

        if (! $user->hasPermissionTo('task-assignment-petitions.update')) {
            return false;
        }


        if ($this->isUserInOverviewDepartment($user)) {
            return true;
        }

        // Người trong phòng ban được giao của đơn thư cũng có quyền cập nhật.
        if ($petition->department_id) {
            return TaskAssignmentUser::where('user_id', $user->id)
                ->where('task_assignment_department_id', $petition->department_id)
                ->exists();
        }

        return false;
    }

    /**
     * Xóa đơn thư — cần có quyền destroy VÀ là người tạo.
     */
    public function delete(User $user, TaskAssignmentPetition $petition): bool
    {
        if (! $user->hasPermissionTo('task-assignment-petitions.destroy')) {
            return false;
        }

        return $this->isUserInOverviewDepartment($user);
    }

    /**
     * Xóa đơn thư hàng loạt — cần quyền bulkDestroy.
     * Phòng ban tổng hợp có toàn quyền, nếu không phải là người tạo của tất cả đơn thư được chọn.
     */
    public function bulkDestroy(User $user): bool
    {
        if (! $user->hasPermissionTo('task-assignment-petitions.bulkDestroy')) {
            return false;
        }

        return $this->isUserInOverviewDepartment($user);
    }

    /**
     * Đổi trạng thái hàng loạt — cần quyền bulkUpdateStatus.
     * Chỉ phòng ban tổng hợp mới được đổi trạng thái hàng loạt (như bulkDestroy).
     */
    public function bulkUpdateStatus(User $user): bool
    {
        if (! $user->hasPermissionTo('task-assignment-petitions.bulkUpdateStatus')) {
            return false;
        }

        return $this->isUserInOverviewDepartment($user);
    }

    /**
     * Đổi trạng thái đơn thư — cần có quyền changeStatus VÀ là người trong phòng ban được giao.
     */
    public function changeStatus(User $user, TaskAssignmentPetition $petition): bool
    {
        if ($petition->processing_status === \App\Modules\TaskAssignment\Enums\PetitionStatusEnum::Completed->value) {
            return false;
        }

        if (! $user->hasPermissionTo('task-assignment-petitions.changeStatus')) {
            return false;
        }



        if ($this->isUserInOverviewDepartment($user)) {
            return true;
        }

        // Người trong phòng ban được giao mới có quyền đổi trạng thái.
        if ($petition->department_id) {
            return TaskAssignmentUser::where('user_id', $user->id)
                ->where('task_assignment_department_id', $petition->department_id)
                ->exists();
        }

        return false;
    }

    /**
     * Mở khóa đơn thư — chỉ phòng ban tổng hợp mới được mở (và phải có quyền manage).
     * Người tạo không liên quan.
     */
    public function unlock(User $user, TaskAssignmentPetition $petition): bool
    {
        if (! $user->hasPermissionTo('task-assignment-petitions.manage')) {
            return false;
        }

        return $this->isUserInOverviewDepartment($user);
    }

    private function isUserInOverviewDepartment(User $user): bool
    {
        $deptIds = TaskAssignmentUser::where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('task_assignment_department_id')
            ->toArray();

        if (empty($deptIds)) {
            return false;
        }

        return \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::whereIn('id', $deptIds)
            ->where('is_petition_overview', true)
            ->exists();
    }
}
