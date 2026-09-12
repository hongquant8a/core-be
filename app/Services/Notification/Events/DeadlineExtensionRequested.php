<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Người thực hiện gửi yêu cầu gia hạn thời hạn → báo cho người đã giao việc để
 * duyệt. Fire ở TaskAssignmentItemExtensionService::request.
 */
class DeadlineExtensionRequested implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentItemExtension $extension,
    ) {}
}
