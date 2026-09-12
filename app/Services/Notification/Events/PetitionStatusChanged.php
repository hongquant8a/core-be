<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Đơn thư đổi trạng thái → báo lại cho người đã lập đơn.
 *
 * Fire ở TaskAssignmentPetitionService::changeStatus.
 */
class PetitionStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentPetition $petition,
        public string $previousStatus,
    ) {}
}
