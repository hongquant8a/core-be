<?php

namespace App\Services\Notification\Events;

use App\Modules\Meeting\Models\Meeting;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Meeting bị hủy (status chuyển từ `published` → `cancelled`).
 *
 * Listener gửi thông báo "Cuộc họp đã bị hủy" cho participants + chair + operator
 * + guests qua channels đã cấu hình ở NotificationEventConfig (giống meeting_published).
 *
 * KHÔNG bắn khi chuyển từ draft → cancelled (chưa publish thì chưa ai biết).
 */
class MeetingCancelled implements ShouldDispatchAfterCommit
{
    public function __construct(public Meeting $meeting) {}
}
