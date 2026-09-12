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
     * Chuyển qua lại giữa "Chưa thực hiện" và "Đang thực hiện".
     *
     * Tạm dừng và huỷ ĐÃ TÁCH sang `pause()` / `cancel()` với quyền riêng, nên ở
     * đây không còn OR ba quyền như trước. Cái OR đó biến `pause` và `cancel`
     * thành quyền chết: có một trong ba là làm được cả ba, quản trị bật/tắt trên
     * màn Vai trò không đổi gì.
     *
     * Vẫn là "người liên quan" chứ không riêng người giao: đẩy việc của mình từ
     * Chưa thực hiện sang Đang thực hiện là thao tác chính đáng của người làm.
     */
    public function changeStatus(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.changeStatus')) {
            return false;
        }

        return $this->isOwnerOrAssigned($user, $item);
    }

    /**
     * Tạm dừng — quyền riêng, và chỉ người đã giao việc.
     *
     * Tạm dừng khoá người thực hiện không cập nhật tiến độ được nữa, nên đây là
     * quyết định của người giao chứ không phải của người đang làm.
     */
    public function pause(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.pause')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /** Huỷ công việc — quyền riêng, và chỉ người đã giao việc. */
    public function cancel(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.cancel')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Trả lại báo cáo (reject) — nghiệp vụ riêng, KHÔNG dùng chung `changeStatus`.
     *
     * Đây là mặt còn lại của việc duyệt: `markDone` là chấp nhận, `reject` là từ
     * chối. Nên nó phải cùng một luật với `markDone` — chỉ người đã giao việc.
     * Trước đây route gác bằng `can:changeStatus`, mà policy đó chỉ đòi "người
     * liên quan", nên chính người thực hiện tự trả lại báo cáo của mình được.
     */
    public function reject(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.reject')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Mở lại công việc đã đóng — nghiệp vụ riêng, cùng luật với `reject`.
     *
     * Mở lại việc đã hoàn thành là đảo ngược quyết định duyệt, nên quyền phải
     * nằm đúng ở người đã ra quyết định đó.
     */
    public function reopen(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.reopen')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Xin gia hạn thời hạn — chỉ NGƯỜI THỰC HIỆN.
     *
     * Cố ý dùng `isAssignee()` chứ không phải `isOwnerOrAssigned()`: helper kia
     * cho cả người giao việc lọt vào, mà họ sửa thẳng `end_at` qua màn sửa công
     * việc được rồi, không phải xin ai.
     */
    public function requestExtension(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-received-tasks.requestExtension')) {
            return false;
        }

        return $this->isAssignee($user, $item);
    }

    /**
     * Duyệt / từ chối gia hạn — cùng luật với `markDone`: chỉ người đã giao việc.
     *
     * KHÔNG gác bằng `changeStatus`: policy đó chỉ đòi "người liên quan", nên
     * người thực hiện sẽ tự duyệt yêu cầu của chính mình. Đây đúng là lỗ hổng
     * `reject`/`reopen` từng mắc trước ngày 11/09/2026.
     */
    public function approveExtension(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->can('my-assigned-tasks.approveExtension')) {
            return false;
        }

        return (int) $item->assigned_by === $user->id;
    }

    /**
     * Cập nhật tiến độ — chỉ người được giao hoặc người giao.
     */
    public function updateProgress(User $user, TaskAssignmentItem $item): bool
    {
        if (! $user->hasPermissionTo('my-received-tasks.updateProgress') && ! $user->hasPermissionTo('task-assignment-documents.updateItem')) {
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
    /**
     * Helper: CHỈ người thực hiện — người giao việc KHÔNG tính.
     * Dùng cho thao tác mà người giao việc vốn đã có đường khác để làm.
     */
    private function isAssignee(User $user, TaskAssignmentItem $item): bool
    {
        $item->loadMissing('users');

        return $item->users->contains('id', $user->id);
    }

    private function isOwnerOrAssigned(User $user, TaskAssignmentItem $item): bool
    {
        if ((int) $item->assigned_by === $user->id) {
            return true;
        }

        // Kiểm tra trong pivot (tránh N+1: load nếu chưa có)
        $item->loadMissing('users');

        return $item->users->contains('id', $user->id);
    }

    // Đã bỏ `checkPauseCancelRestriction()`: nó thò tay đọc
    // `request('processing_status')` từ trong policy để đoán người dùng định làm
    // gì, rồi mới quyết định có siết quyền sở hữu hay không. Kiểu viết đó gắn
    // policy vào hình dạng HTTP request — đổi tên trường hoặc gọi policy từ chỗ
    // không có request (job, command, test) là nó âm thầm cho qua. Tạm dừng và
    // huỷ nay có `pause()` / `cancel()` riêng, luật nằm ngay trong luật.
}
