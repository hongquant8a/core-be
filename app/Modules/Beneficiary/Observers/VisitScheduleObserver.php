<?php

namespace App\Modules\Beneficiary\Observers;

use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Services\Notification\Services\ReminderScheduler;

/**
 * Bám sát App\Modules\Scheduling\Observers\ScheduleObserver — ghi/huỷ reminder rows
 * là data-integrity (chuẩn bị dữ liệu), không phải "gửi", nên đặt ở Observer là đúng
 * theo CLAUDE.md §EDA mục 3. Chỉ hành vi gửi thật (Zalo/FCM) mới nằm ở Listener/Notification.
 */
class VisitScheduleObserver
{
    public function __construct(protected ReminderScheduler $scheduler)
    {
    }

    public function saved(VisitSchedule $schedule): void
    {
        $isPending = $schedule->status === ScheduleStatusEnum::Pending->value;

        if ($isPending && ($schedule->wasRecentlyCreated || $schedule->wasChanged('scheduled_date'))) {
            $this->scheduler->scheduleFor($schedule);

            return;
        }

        if (! $isPending && $schedule->wasChanged('status')) {
            $this->scheduler->cancelPending($schedule);
        }
    }

    public function deleted(VisitSchedule $schedule): void
    {
        $this->scheduler->cancelPending($schedule);
    }
}
