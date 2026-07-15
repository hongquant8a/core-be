<?php

namespace App\Modules\Scheduling\Observers;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\ScheduleStatus;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Support\Facades\Event;

class ScheduleObserver
{
    private const NOTIFY_FIELDS = [
        'content',
        'date_time',
        'location',
    ];

    public function __construct(protected ReminderScheduler $scheduler) {}

    public function saved(Schedule $schedule): void
    {
        $status = $schedule->status;
        $originalStatus = $schedule->getOriginal('status');

        // Extract raw integer value from Enum if needed
        $statusVal = $status instanceof ScheduleStatus ? $status->value : (int)$status;
        $originalStatusVal = $originalStatus instanceof ScheduleStatus ? $originalStatus->value : (int)$originalStatus;

        $isPublishedNow = $statusVal === ScheduleStatus::PUBLISHED->value;
        $wasPublishedBefore = $originalStatusVal === ScheduleStatus::PUBLISHED->value;

        // 1. Transition: Draft -> Published
        if ($isPublishedNow && (!$wasPublishedBefore || $schedule->wasRecentlyCreated)) {
            $this->scheduler->scheduleFor($schedule);
            return;
        }

        // 2. Already published + key fields changed → reschedule nếu date_time đổi
        if ($isPublishedNow && $wasPublishedBefore) {
            if ($schedule->wasChanged('date_time')) {
                // date_time đổi → deadline mới → tính lại remind_at cho tất cả reminders
                $this->scheduler->scheduleFor($schedule);
            }
            return;
        }

        // 3. Transition: Published -> Draft/Pending/Cancelled/etc. (Unpublished)
        if (!$isPublishedNow && $wasPublishedBefore) {
            $this->scheduler->cancelPending($schedule);
            return;
        }
    }

    public function deleted(Schedule $schedule): void
    {
        $originalStatus = $schedule->getOriginal('status');
        $originalStatusVal = $originalStatus instanceof ScheduleStatus ? $originalStatus->value : (int)$originalStatus;

        if ($originalStatusVal !== ScheduleStatus::PUBLISHED->value) {
            $this->scheduler->cancelPending($schedule);
        }
    }
}
