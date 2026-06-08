<?php

namespace App\Modules\Scheduling\Observers;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\ScheduleStatus;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Services\ScheduleReminderScheduler;
use Illuminate\Support\Facades\Event;

class ScheduleObserver
{
    private const NOTIFY_FIELDS = [
        'content',
        'date_time',
        'location',
    ];

    public function __construct(protected ScheduleReminderScheduler $scheduler) {}

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
            Event::dispatch(new SchedulePublished($schedule));
            return;
        }

        // 2. Already published, but key fields changed
        if ($isPublishedNow && $wasPublishedBefore) {
            $changedFields = array_filter(
                self::NOTIFY_FIELDS,
                fn ($f) => $schedule->wasChanged($f)
            );

            if (!empty($changedFields)) {
                Event::dispatch(new ScheduleUpdated($schedule, array_values($changedFields)));
            }
            return;
        }

        // 3. Transition: Published -> Draft/Pending/Cancelled/etc. (Unpublished)
        if (!$isPublishedNow && $wasPublishedBefore) {
            Event::dispatch(new ScheduleCancelled($schedule));
            return;
        }
    }

    public function deleted(Schedule $schedule): void
    {
        $originalStatus = $schedule->getOriginal('status');
        $originalStatusVal = $originalStatus instanceof ScheduleStatus ? $originalStatus->value : (int)$originalStatus;

        if ($originalStatusVal === ScheduleStatus::PUBLISHED->value) {
            Event::dispatch(new ScheduleCancelled($schedule));
        } else {
            $this->scheduler->cancelPending($schedule);
        }
    }
}
