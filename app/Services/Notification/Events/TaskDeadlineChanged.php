<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Người quản lý sửa thẳng thời hạn qua màn sửa công việc → báo cho người thực hiện.
 *
 * Vì sao cần: hệ thống cố ý giữ hai đường đổi thời hạn — xin gia hạn (có duyệt,
 * có lịch sử, có thông báo) và quản lý sửa trực tiếp. Không có sự kiện này thì
 * đường thứ hai đổi hạn sau lưng người thực hiện, trong khi đường thứ nhất báo
 * đầy đủ. Bất đối xứng đó là lỗi, không phải thiết kế.
 *
 * Fire ở TaskAssignmentItemService::update khi `end_at` thực sự đổi.
 */
class TaskDeadlineChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItem $item,
        public ?string $previousEndAt,
    ) {}
}
