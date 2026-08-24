<?php

namespace App\Modules\TaskAssignment\Listeners;

use App\Modules\Core\Events\UsersDeleting;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chặn xóa người dùng đang được giao công việc chưa hoàn tất.
 *
 * Trước đây luật này nằm trong `Core\Services\UserService`, buộc Core phải query bảng
 * `task_assignment_item_user` của phân hệ. Nay phân hệ tự đăng ký luật của mình qua
 * event `UsersDeleting`, Core không còn biết gì về bảng của phân hệ.
 *
 * Chạy đồng bộ có chủ đích: ném ValidationException để dừng thao tác xóa.
 */
class BlockUserDeletionWithActiveTasks
{
    public function handle(UsersDeleting $event): void
    {
        if (empty($event->userIds)) {
            return;
        }

        $blocked = DB::table('task_assignment_item_user as tiu')
            ->join('task_assignment_items as ti', 'ti.id', '=', 'tiu.task_assignment_item_id')
            ->whereIn('tiu.user_id', $event->userIds)
            ->whereIn('tiu.assignment_status', ['assigned', 'done'])
            ->whereNotIn('ti.processing_status', ['done', 'cancelled'])
            ->select('tiu.user_id', DB::raw('count(*) as task_count'))
            ->groupBy('tiu.user_id')
            ->get();

        if ($blocked->isEmpty()) {
            return;
        }

        $names = User::whereIn('id', $blocked->pluck('user_id'))->pluck('name', 'id');
        $details = $blocked
            ->map(fn ($row) => ($names[$row->user_id] ?? "User #{$row->user_id}").": {$row->task_count} công việc")
            ->implode('; ');

        throw ValidationException::withMessages([
            'user_id' => ["Không thể xóa user đang có công việc chưa hoàn tất. Vui lòng chuyển công việc trước. Chi tiết: {$details}"],
        ]);
    }
}
