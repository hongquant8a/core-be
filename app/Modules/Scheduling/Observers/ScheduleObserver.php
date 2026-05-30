<?php

namespace App\Modules\Scheduling\Observers;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Services\ScheduleReminderScheduler;
use Illuminate\Support\Facades\Event;

class ScheduleObserver
{
    private const NOTIFY_FIELDS = [
        'content',
        'event_date',
        'start_time',
        'location',
    ];

    public function __construct(protected ScheduleReminderScheduler $scheduler) {}

    public function saved(Schedule $schedule): void
    {
        $status = $schedule->status;
        $originalStatus = $schedule->getOriginal('status');

        // Extract raw string value from Enum if needed
        $statusVal = $status instanceof ScheduleStatusEnum ? $status->value : $status;
        $originalStatusVal = $originalStatus instanceof ScheduleStatusEnum ? $originalStatus->value : $originalStatus;

        $isPublishedNow = $statusVal === ScheduleStatusEnum::Published->value;
        $wasPublishedBefore = $originalStatusVal === ScheduleStatusEnum::Published->value;

        // 1. Transition: Draft/Pending -> Published
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
        $originalStatusVal = $originalStatus instanceof ScheduleStatusEnum ? $originalStatus->value : $originalStatus;

        if ($originalStatusVal === ScheduleStatusEnum::Published->value) {
            Event::dispatch(new ScheduleCancelled($schedule));
        } else {
            $this->scheduler->cancelPending($schedule);
        }
    }
}
