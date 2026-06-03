<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleNotification;
use App\Modules\Scheduling\Enums\{NotificationChannel, NotificationStatus, SessionType};
use App\Modules\Scheduling\Jobs\SendScheduleNotificationJob;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Resolve all recipient user IDs for a given schedule, expanding groups.
     */
    public function expandRecipients(Schedule $schedule): array
    {
        $schedule->load(['recipients.group.members']);

        $userIds = [];

        if ($schedule->host_id) {
            $userIds[] = $schedule->host_id;
        }
        if ($schedule->driver_id) {
            $userIds[] = $schedule->driver_id;
        }

        foreach ($schedule->recipients as $recipient) {
            if ($recipient->user_id) {
                $userIds[] = $recipient->user_id;
            }
            if ($recipient->group_id && $recipient->group) {
                foreach ($recipient->group->members as $member) {
                    $userIds[] = $member->id;
                }
            }
        }

        return array_unique($userIds);
    }

    /**
     * Generate instant notifications.
     */
    public function generateNotifications(Schedule $schedule, string $eventKey): void
    {
        $userIds = $this->expandRecipients($schedule);

        // Cancel any pending notifications for this schedule first
        ScheduleNotification::where('schedule_id', $schedule->id)
            ->where('status', NotificationStatus::PENDING->value)
            ->update(['status' => NotificationStatus::CANCELLED->value]);

        $channels = [NotificationChannel::APP, NotificationChannel::FCM];
        $now = Carbon::now();

        foreach ($userIds as $userId) {
            foreach ($channels as $channel) {
                ScheduleNotification::create([
                    'organization_id' => $schedule->organization_id,
                    'schedule_id'     => $schedule->id,
                    'user_id'         => $userId,
                    'channel'         => $channel->value,
                    'remind_at'       => $now,
                    'status'          => NotificationStatus::PENDING->value,
                    'retry_count'     => 0,
                ]);
            }
        }
    }

    /**
     * Generate scheduled reminders.
     */
    public function generateReminders(Schedule $schedule): void
    {
        $userIds = $this->expandRecipients($schedule);
        $schedule->load('reminders');

        $eventDatetime = $this->getEventDatetime($schedule);
        $now = Carbon::now();

        foreach ($schedule->reminders as $reminder) {
            $remindAt = $eventDatetime->copy()->subMinutes($reminder->minutes_before);

            // Only queue future reminders
            if ($remindAt->isAfter($now)) {
                $channels = $reminder->channels;
                if (!is_array($channels)) {
                    continue;
                }

                foreach ($userIds as $userId) {
                    foreach ($channels as $channelStr) {
                        ScheduleNotification::create([
                            'organization_id' => $schedule->organization_id,
                            'schedule_id'     => $schedule->id,
                            'user_id'         => $userId,
                            'channel'         => strtoupper($channelStr),
                            'remind_at'       => $remindAt,
                            'status'          => NotificationStatus::PENDING->value,
                            'retry_count'     => 0,
                        ]);
                    }
                }
            }
        }
    }

    public function publish(Schedule $schedule): void
    {
        $this->generateNotifications($schedule, 'schedule_published');
        $this->generateReminders($schedule);
        $this->dispatchPending($schedule);
    }

    public function update(Schedule $schedule): void
    {
        $this->generateNotifications($schedule, 'schedule_updated');
        $this->generateReminders($schedule);
        $this->dispatchPending($schedule);
    }

    public function cancel(Schedule $schedule): void
    {
        ScheduleNotification::where('schedule_id', $schedule->id)
            ->where('status', NotificationStatus::PENDING->value)
            ->update(['status' => NotificationStatus::CANCELLED->value]);

        $this->generateNotifications($schedule, 'schedule_cancelled');
        $this->dispatchPending($schedule);
    }

    /**
     * Scan schedule_notifications for PENDING entries and dispatch them.
     */
    public function dispatchPending(Schedule $schedule): void
    {
        $pendingNotifications = ScheduleNotification::where('schedule_id', $schedule->id)
            ->where('status', NotificationStatus::PENDING->value)
            ->get();

        foreach ($pendingNotifications as $notification) {
            $delay = $notification->remind_at->isFuture() ? $notification->remind_at : null;

            $job = SendScheduleNotificationJob::dispatch($notification->id)->onQueue('notifications');
            if ($delay) {
                $job->delay($delay);
            }
        }
    }

    /**
     * Helper to get full event datetime.
     */
    protected function getEventDatetime(Schedule $schedule): Carbon
    {
        $date = $schedule->event_date;
        $timeStr = $schedule->start_time;

        if (!$timeStr) {
            $sessionVal = $schedule->session instanceof SessionType
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
}
