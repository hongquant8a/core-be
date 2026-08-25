<?php

namespace App\Modules\TaskAssignment\Policies;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Phân quyền báo cáo công việc theo hai trục tách bạch, giống đơn thư:
 *  - QUYỀN  (`my-received-tasks.report`) quyết định ĐƯỢC LÀM GÌ.
 *  - PHẠM VI quyết định LÀM TRÊN BÁO CÁO NÀO: người nộp báo cáo, hoặc người
 *    giao việc của công việc chứa báo cáo đó.
 *
 * Trước đây `update`/`destroy` chỉ gác `permission:my-received-tasks.report`,
 * không kiểm sở hữu — bất kỳ ai có quyền báo cáo đều sửa/xoá được báo cáo của
 * người khác. Giao diện che đi bằng cách không hiện nút, nhưng gọi thẳng API
 * thì vẫn qua; che ở giao diện không phải là phân quyền.
 *
 * `index`/`show`/`store` vẫn gác bằng permission như cũ — phạm vi đọc là việc
 * riêng, chưa đụng tới ở đây.
 */
class TaskAssignmentItemReportPolicy
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

    /**
     * Xem chi tiết một báo cáo.
     *
     * Phạm vi ĐỌC bám theo công việc chứ không theo báo cáo: ai liên quan tới
     * công việc (người giao hoặc bất kỳ người được giao nào) đều đọc được mọi
     * báo cáo của công việc đó, cộng thêm người xem màn Tổng quan / Trình diễn.
     * Nếu siết đọc về đúng người nộp thì `index` (trả cả danh sách) và `show`
     * sẽ nói hai điều khác nhau về cùng một báo cáo.
     */
    public function view(User $user, TaskAssignmentItemReport $report): bool
    {
        if (! $user->can('my-received-tasks.report')) {
            return false;
        }

        if ($user->can('task-overview.index') || $user->can('presentation.index')) {
            return true;
        }

        $report->loadMissing('item.users');
        $item = $report->item;

        if (! $item) {
            return false;
        }

        return (int) $item->assigned_by === $user->id
            || $item->users->contains('id', $user->id);
    }

    public function update(User $user, TaskAssignmentItemReport $report): bool
    {
        return $user->can('my-received-tasks.report')
            && $this->inScope($user, $report);
    }

    public function delete(User $user, TaskAssignmentItemReport $report): bool
    {
        return $user->can('my-received-tasks.report')
            && $this->inScope($user, $report);
    }

    /**
     * Báo cáo có thuộc phạm vi thao tác của user không.
     *
     * Hai vai được thao tác: người đã nộp báo cáo (sửa/xoá báo cáo của chính
     * mình) và người giao việc của công việc chứa báo cáo (`assigned_by`).
     * Người cùng được giao việc nhưng không phải người nộp thì không — báo cáo
     * là phát ngôn của một người cụ thể.
     */
    private function inScope(User $user, TaskAssignmentItemReport $report): bool
    {
        if ((int) $report->reporter_user_id === $user->id) {
            return true;
        }

        $report->loadMissing('item');

        return (int) $report->item?->assigned_by === $user->id;
    }
}
