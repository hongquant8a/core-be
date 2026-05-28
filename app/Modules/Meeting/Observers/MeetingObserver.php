<?php

namespace App\Modules\Meeting\Observers;

use App\Modules\Meeting\Models\Meeting;
use App\Services\Notification\Events\MeetingUpdated;
use App\Services\Notification\Services\MeetingReminderScheduler;
use Illuminate\Support\Facades\Event;

class MeetingObserver
{
    /** Fields "quan trọng" — khi đổi → bắn MeetingUpdated event. */
    private const NOTIFY_FIELDS = [
        'title',
        'start_time',
        'end_time',
        'meeting_location_id',
        'content',
    ];

    public function __construct(private MeetingReminderScheduler $scheduler) {}

    public function updated(Meeting $meeting): void
    {
        // Chỉ bắn MeetingUpdated nếu:
        //   - status TRƯỚC khi update đã là 'published' (đã ban hành)
        //   - VÀ có ít nhất 1 field "quan trọng" thay đổi
        // Không trùng với MeetingPublished (chỉ fire khi status chuyển draft → published).
        $wasPublished = $meeting->getOriginal('status') === 'published';
        $changedNotifyFields = array_filter(
            self::NOTIFY_FIELDS,
            fn ($f) => $meeting->wasChanged($f),
        );

        if ($wasPublished && ! empty($changedNotifyFields)) {
            Event::dispatch(new MeetingUpdated($meeting, array_values($changedNotifyFields)));
        }
    }

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
