<?php

namespace App\Modules\TaskAssignment\Policies;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskAssignmentItemPolicy
{
    use HandlesAuthorization;

    /**
     * Ai có quyền `task-overview.manageAll` thì bypass mọi check ownership.
     * Kiểm theo QUYỀN, không theo tên vai trò — vai trò là dữ liệu, đổi tên được.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('task-overview.manageAll')) {
            return true;
        }

        return null;
    }

    /**
     * Xem danh sách — bất kỳ màn nào có hiển thị công việc:
     * Văn bản giao việc (chi tiết văn bản), Đang giao, Được giao, Tổng quan, Trình diễn.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-documents.index')
            || $user->hasPermissionTo('my-assigned-tasks.index')
            || $user->hasPermissionTo('my-received-tasks.index')
            || $user->hasPermissionTo('task-overview.index')
            || $user->hasPermissionTo('presentation.index');
    }

    /**
     * Xem chi tiết — dùng chung quyền index (xem được danh sách thì xem được chi tiết).
     */
    public function view(User $user, TaskAssignmentItem $item): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Tạo mới — công việc chỉ được tạo trong màn Văn bản giao việc.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-documents.storeItem');
    }

    /**
     * Đổi trạng thái hàng loạt — dùng chung quyền update.
     * Quy tắc kiểm tra quyền sở hữu (ownership) chi tiết được xử lý ở Service.
     */
    public function bulkUpdateStatus(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-documents.updateItem');
    }

    /**
     * Xóa hàng loạt — dùng chung quyền destroy.
     * Quy tắc kiểm tra quyền sở hữu (ownership) chi tiết được xử lý ở Service.
     */
    public function bulkDestroy(User $user): bool
    {
        return $user->hasPermissionTo('task-assignment-documents.destroyItem');
    }

    /**
     * Cập nhật — cần quyền sửa công việc trong văn bản VÀ là người liên quan đến task:
     *   - Người giao (assigned_by)
     *   - Người được giao (pivot task_assignment_item_user)
     *
     * Trường hợp đặc biệt: Quản lý công việc (có quyền update) không cần check ownership
     * nếu đang quản lý phòng ban — nhưng check đó nằm ở service-level department scope.
     */
    public function update(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-documents.updateItem')) {
            return false;
        }

        if (! $this->checkPauseCancelRestriction($user, $item)) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Xóa — cần quyền xóa công việc trong văn bản VÀ là người giao.
     */
    public function delete(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('task-assignment-documents.destroyItem')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Đổi trạng thái (pause/cancel/changeStatus) — người liên quan đến task.
     */
    public function changeStatus(User $user, TaskAssignmentItem $item): bool
    {
        $hasPermission = $user->hasPermissionTo('my-assigned-tasks.changeStatus')
            || $user->hasPermissionTo('my-assigned-tasks.pause')
            || $user->hasPermissionTo('my-assigned-tasks.cancel');

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
        if (! $user->hasPermissionTo('my-received-tasks.updateProgress') && ! $user->hasPermissionTo('task-assignment-documents.updateItem')) {
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
        if (! $user->hasPermissionTo('my-assigned-tasks.markDone')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Điều chuyển công việc — cần quyền điều chuyển VÀ là người liên quan đến task.
     *
     * Trước đây route chỉ gác permission. Service có nhánh "manager chuyển hộ":
     * user không nằm trong pivot thì nó lấy assignee `main` để chuyển — nghĩa là
     * bất kỳ ai có quyền điều chuyển cũng chuyển được việc của người khác. Nhánh
     * đó vẫn giữ (đúng cho người giao việc), nhưng nay chỉ người liên quan mới
     * vào được tới đó.
     */
    public function transfer(User $user, TaskAssignmentItem $item): bool
    {
        // Dùng can() thay hasPermissionTo(): quyền chưa seed thì trả false thay vì ném.
        $hasPermission = $user->can('my-assigned-tasks.transfer')
            || $user->can('my-received-tasks.transfer');

        return $hasPermission && $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Ghi chú / trao đổi trên công việc — cần quyền ghi chú VÀ là người liên quan.
     *
     * `my-received-tasks.note` nằm trong bộ quyền mặc định của vai trò Nhân viên,
     * nên khi route chỉ gác permission thì mọi nhân viên ghi chú được vào công
     * việc bất kỳ, kể cả việc không liên quan tới mình.
     */
    public function note(User $user, TaskAssignmentItem $item): bool
    {
        $hasPermission = $user->can('my-assigned-tasks.note')
            || $user->can('my-received-tasks.note');

        return $hasPermission && $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Nộp báo cáo cho công việc — chỉ người liên quan tới chính công việc đó.
     * KHÔNG nới theo `task-overview.index` như quyền đọc bên dưới: quyền đó là
     * quyền đọc dữ liệu tổng hợp, không hàm ý được GHI vào công việc của người
     * khác.
     */
    public function report(User $user, TaskAssignmentItem $item): bool
    {
        return $user->can('my-received-tasks.report')
            && $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Đọc danh sách báo cáo của công việc.
     *
     * Rộng hơn `report()` một bậc, và căn cứ là ngữ nghĩa sẵn có của cây quyền:
     * `task-overview.index` và `presentation.index` đã là quyền ĐỌC dữ liệu công
     * việc toàn tổ chức — xem 7 route thống kê trong `task_assignment_item.php`
     * (`stats-by-user`, `stats-by-department`, `overdue`, ...) đều gác đúng hai
     * quyền này. Báo cáo là một mặt của cùng khối dữ liệu đó, nên ai đọc được
     * thống kê thì đọc được báo cáo; siết hơn ở đây là mâu thuẫn với chính BE.
     *
     * Ghi để không tái diễn: căn cứ là cây quyền, KHÔNG phải "nếu siết thì màn
     * Tổng quan gãy". Frontend theo backend, không phải chiều ngược lại.
     */
    public function viewReports(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-received-tasks.report')) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item)
            || $user->can('task-overview.index')
            || $user->can('presentation.index');
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
