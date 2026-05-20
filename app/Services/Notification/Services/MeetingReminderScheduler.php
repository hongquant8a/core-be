<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

class MeetingReminderScheduler
{
    /**
     * (Re)create pending reminders cho meeting theo schedules của 3 reminder event config (module=meeting).
     */
    public function scheduleFor(Meeting $meeting): void
    {
        // Hủy pending nếu meeting bị cancelled hoặc đã kết thúc (end_time đã qua).
        $isFinished = $meeting->status === 'cancelled'
            || ($meeting->end_time && $meeting->end_time->isPast());

        if (! $meeting->start_time || $isFinished) {
            $this->cancelPending($meeting);

            return;
        }

        // Chỉ schedule khi meeting đã phát hành — tránh tạo reminder cho draft.
        if ($meeting->status !== 'published') {
            return;
        }

        $organizationId = (int) $meeting->organization_id;
        if ($organizationId === 0) {
            return;
        }

        // Xóa pending auto reminders cũ; giữ nguyên reminder thủ công (reminder_type=manual).
        MeetingReminder::where('meeting_id', $meeting->id)
            ->where('status', 'pending')
            ->whereNotNull('notification_schedule_id')
            ->delete();

        $reminderEventKeys = [
            NotificationEventEnum::MeetingReminderBefore->value,
            NotificationEventEnum::MeetingReminderOn->value,
            NotificationEventEnum::MeetingReminderAfter->value,
        ];

        $configs = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Meeting->value)
            ->where('organization_id', $organizationId)
            ->whereIn('event_key', $reminderEventKeys)
            ->get();

        foreach ($configs as $config) {
            foreach ($config->schedules as $schedule) {
                $remindAt = $this->computeRemindAt($meeting, $schedule);
                if ($remindAt === null) {
                    continue;
                }

                MeetingReminder::create([
                    'organization_id' => $organizationId,
                    'meeting_id' => $meeting->id,
                    'reminder_type' => 'scheduled',
                    'notification_schedule_id' => $schedule->id,
                    'moment' => $schedule->moment,
                    'scheduled_at' => $remindAt,
                    'status' => 'pending',
                ]);
            }
        }
    }

    public function cancelPending(Meeting $meeting): void
    {
        MeetingReminder::where('meeting_id', $meeting->id)
            ->where('status', 'pending')
            ->whereNotNull('notification_schedule_id')
            ->update(['status' => 'cancelled']);
    }

    private function computeRemindAt(Meeting $meeting, NotificationSchedule $schedule): ?\Carbon\Carbon
    {
        $start = $meeting->start_time;
        if (! $start) {
            return null;
        }

        return match ($schedule->moment) {
            'before' => $schedule->offset_minutes ? $start->copy()->subMinutes($schedule->offset_minutes) : null,
            'on' => $start->copy(),
            'after' => $schedule->offset_minutes ? $start->copy()->addMinutes($schedule->offset_minutes) : null,
            default => null,
        };
    }
}
