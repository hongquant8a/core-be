<?php

namespace App\Services\Notification\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Modules\Scheduling\Enums\ReminderStatusEnum;
use App\Services\Notification\Jobs\SendScheduleReminderJob;
use Carbon\Carbon;

class ScheduleReminderScheduler
{
    /**
     * Recompute and schedule reminders for a published schedule.
     */
    public function scheduleFor(Schedule $schedule): void
    {
        $eventDatetime = $this->getEventDatetime($schedule);

        // Load reminders if not loaded
        $schedule->load('reminders');

        foreach ($schedule->reminders as $reminder) {
            $remindAt = $eventDatetime->copy()->subMinutes($reminder->minutes_before);
            
            $reminder->update([
                'remind_at' => $remindAt,
                'status' => ReminderStatusEnum::Pending->value,
            ]);

            $recipients = $this->resolveRecipients($schedule);

            if ($remindAt->isFuture()) {
                foreach ($recipients as $user) {
                    SendScheduleReminderJob::dispatch(
                         $schedule->id,
                         $reminder->id,
                         $user->id
                    )->delay($remindAt)->onQueue('notifications');
                }
            }
        }
    }

    /**
     * Cancel pending reminders for a schedule.
     */
    public function cancelPending(Schedule $schedule): void
    {
        $schedule->reminders()->where('status', ReminderStatusEnum::Pending->value)->update([
            'status' => ReminderStatusEnum::Cancelled->value,
        ]);
    }

    /**
     * Helper to get event datetime.
     */
    public function getEventDatetime(Schedule $schedule): Carbon
    {
        $date = $schedule->event_date;
        $timeStr = $schedule->start_time;

        if (!$timeStr) {
            $sessionVal = $schedule->session instanceof \App\Modules\Scheduling\Enums\SessionTypeEnum
                ? $schedule->session->value
                : $schedule->session;

            $timeStr = match ($sessionVal) {
                'S' => '07:30:00',
                'C' => '13:30:00',
                'T' => '19:30:00',
                default => '08:00:00',
            };
        }

        if ($date instanceof Carbon) {
            $dateStr = $date->format('Y-m-d');
        } else {
            $dateStr = Carbon::parse($date)->format('Y-m-d');
        }

        return Carbon::parse("{$dateStr} {$timeStr}");
    }

    /**
     * Resolve all recipient users of a schedule, expanding groups.
     * Returns an array of User models.
     */
    public function resolveRecipients(Schedule $schedule): array
    {
        $schedule->load(['recipients.user', 'recipients.group.users']);

        $users = [];

        foreach ($schedule->recipients as $recipient) {
            if ($recipient->user) {
                $users[$recipient->user->id] = $recipient->user;
            }
        }

        return array_values($users);
    }
}
