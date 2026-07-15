<?php

namespace App\Modules\Meeting\Observers;

use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Events\MeetingUpdated;
use App\Services\Notification\Events\MeetingPublished;
use App\Services\Notification\Events\MeetingCancelled;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Support\Facades\Event;

class MeetingObserver
{
    public function __construct(private \App\Services\Notification\Services\ReminderScheduler $scheduler) {}

    public function saved(Meeting $meeting): void
    {
        // Cancel pending reminders nếu meeting bị hủy hoặc đã kết thúc.
        $isFinished = $meeting->status === 'cancelled' || $meeting->status === 'completed';

        if ($isFinished) {
            $this->scheduler->cancelPending($meeting);

            return;
        }

        // Schedule reminders — chỉ khi đã phát hành VÀ start_time hoặc status thay đổi
        if ($meeting->status === 'published'
            && ($meeting->wasChanged(['start_time', 'status']) || $meeting->wasRecentlyCreated)) {
            $this->scheduler->scheduleFor($meeting);
        }
    }

    public function deleted(Meeting $meeting): void
    {
        $this->scheduler->cancelPending($meeting);
    }
}
