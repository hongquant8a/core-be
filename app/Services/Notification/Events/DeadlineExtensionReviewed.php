<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Người giao việc đã duyệt hoặc từ chối yêu cầu gia hạn → báo lại cho người xin.
 *
 * Cố ý gộp duyệt và từ chối vào MỘT sự kiện, phân nhánh nội dung theo
 * `$extension->status` trong ContentBuilder: ít file hơn hai sự kiện tách rời, và
 * quản trị cũng chỉ cần một công tắc bật/tắt thay vì hai.
 *
 * Fire ở TaskAssignmentItemExtensionService::approve và ::reject.
 */
class DeadlineExtensionReviewed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItemExtension $extension,
    ) {}
}
