<?php

namespace App\Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phát trước khi xóa người dùng (đơn lẻ hoặc hàng loạt).
 *
 * Đây là điểm mở rộng để từng phân hệ tự chặn việc xóa khi còn dữ liệu ràng buộc:
 * listener ném `ValidationException` là thao tác xóa dừng lại. Nhờ vậy Core không cần
 * biết bảng của bất kỳ phân hệ nào.
 *
 * Listener PHẢI chạy đồng bộ (không `ShouldQueue`), nếu không exception sẽ không kịp
 * chặn thao tác xóa.
 */
class UsersDeleting
{
    use Dispatchable;

    /**
     * @param  array<int>  $userIds
     */
    public function __construct(public array $userIds) {}
}
