<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingReminder;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

class MeetingReminderScheduler
{
    /**
     * (Re)create pending reminders cho meeting theo schedules của 3 reminder event config (module=meeting).
     * Chỉ xóa/recreate PRESET — CUSTOM per-record reminders giữ nguyên.
     */
    public function scheduleFor(Meeting $meeting): void
    {
        $isFinished = $meeting->status === 'cancelled'
            || ($meeting->end_time && $meeting->end_time->isPast());

        if (! $meeting->start_time || $isFinished) {
            $this->cancelPending($meeting);

            return;
        }

        if ($meeting->status !== 'published') {
            return;
        }

        $organizationId = (int) $meeting->organization_id;
        if ($organizationId === 0) {
            return;
        }

        // Chỉ xóa pending PRESET reminders; giữ CUSTOM per-record reminders.
        MeetingReminder::where('meeting_id', $meeting->id)
            ->where('status', 'pending')
            ->where('source', 'PRESET')
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
                $remindAt = $this->computeRemindAt($meeting->start_time, $schedule->moment, (int) $schedule->offset_minutes);
                if ($remindAt === null) {
                    continue;
                }

                MeetingReminder::create([
                    'organization_id'          => $organizationId,
                    'meeting_id'               => $meeting->id,
                    'reminder_type'            => 'scheduled',
                    'notification_schedule_id' => $schedule->id,
                    'moment'                   => $schedule->moment,
                    'offset_minutes'           => (int) $schedule->offset_minutes,
                    'source'                   => 'PRESET',
                    'scheduled_at'             => $remindAt,
                    'remind_at'                => $remindAt,
                    'status'                   => 'pending',
                ]);
            }
        }

        // Compute remind_at cho tất cả pending reminders (PRESET vừa tạo + CUSTOM từ per-record).
        $allPending = MeetingReminder::where('meeting_id', $meeting->id)
            ->where('status', 'pending')
            ->get();

        foreach ($allPending as $reminder) {
            if ($reminder->remind_at !== null) {
                continue; // đã được set lúc tạo PRESET ở trên
            }
            $remindAt = $this->computeRemindAt($meeting->start_time, $reminder->moment, (int) $reminder->offset_minutes);
            if ($remindAt !== null) {
                $reminder->update([
                    'remind_at'    => $remindAt,
                    'scheduled_at' => $remindAt,
                ]);
            }
        }
    }

    public function cancelPending(Meeting $meeting): void
    {
        MeetingReminder::where('meeting_id', $meeting->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function computeRemindAt(?\Carbon\Carbon $start, string $moment, int $offsetMinutes): ?\Carbon\Carbon
    {
        if (! $start) {
            return null;
        }

        return match ($moment) {
            'before' => $offsetMinutes ? $start->copy()->subMinutes($offsetMinutes) : null,
            'on'     => $start->copy(),
            'after'  => $offsetMinutes ? $start->copy()->addMinutes($offsetMinutes) : null,
            default  => null,
        };
    }
}
