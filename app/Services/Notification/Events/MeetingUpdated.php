<?php

namespace App\Services\Notification\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Meeting đã ban hành (status=published) bị cập nhật các trường quan trọng
 * (title / start_time / end_time / meeting_location_id / content) → fire event
 * để gửi thông báo "cập nhật" cho người tham dự.
 *
 * @param  list<string>  $changedFields  Danh sách field đã đổi (cho diff content nếu cần).
 */
class MeetingUpdated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Meeting $meeting,
        public array $changedFields = [],
    ) {}
}
