<?php

namespace App\Services\Notification\Events;

use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Có đơn thư mới → báo cho người đại diện phòng ban tiếp nhận.
 *
 * Đơn thư không có danh sách người thực hiện như công việc, chỉ có phòng ban
 * (`department_id`), nên người đại diện phòng ban là địa chỉ đúng nhất.
 *
 * Fire ở TaskAssignmentPetitionService::store.
 */
class PetitionCreated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public TaskAssignmentPetition $petition,
    ) {}
}
