<?php

namespace App\Modules\Meeting\Observers;

use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Services\MeetingReminderScheduler;

class MeetingObserver
{
    public function __construct(private MeetingReminderScheduler $scheduler) {}

    public function saved(Meeting $meeting): void
    {
        if (in_array($meeting->status, ['cancelled', 'completed'], true)) {
            $this->scheduler->cancelPending($meeting);

            return;
        }

        if ($meeting->wasChanged(['start_time', 'status']) || $meeting->wasRecentlyCreated) {
            $this->scheduler->scheduleFor($meeting);
        }
    }

    public function deleted(Meeting $meeting): void
    {
        $this->scheduler->cancelPending($meeting);
    }
}
