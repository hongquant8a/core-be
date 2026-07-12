<?php

namespace App\Services\Notification\Events;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Phát khi 1 user mới được giao 1 task (item) trong văn bản đã ban hành.
 *
 * Trigger points:
 * - TaskAssignmentItemService::store (assignees ban đầu của item nếu doc đã issued)
 * - TaskAssignmentItemService::update -> syncUsers (chỉ user mới thêm vào)
 * - TaskAssignmentTransferService::transfer (user nhận chuyển)
 *
 * KHÔNG phát khi document còn draft — đợi DocumentIssued cover.
 */
class TaskAssigned implements ShouldDispatchAfterCommit
{
    public function __construct(public TaskAssignmentItem $item, public User $user) {}
}
