<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Công việc bị tạm dừng, huỷ hoặc mở lại → báo cho người thực hiện.
 *
 * Gộp ba thao tác vào MỘT sự kiện, phân nhánh nội dung theo trạng thái mới: ít
 * file hơn ba sự kiện tách rời, và quản trị chỉ cần một công tắc thay vì ba.
 *
 * Trước đây cả ba đều câm lặng: tạm dừng khoá người thực hiện không cập nhật
 * tiến độ được nữa mà họ chỉ phát hiện khi bấm vào và thấy lỗi; huỷ thì họ vẫn
 * tưởng đang phải làm; mở lại thì phải làm tiếp mà không biết.
 *
 * Fire ở TaskAssignmentItemService::changeStatus (paused/cancelled) và ::reopen.
 */
class TaskStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItem $item,
        public string $previousStatus,
    ) {}
}
