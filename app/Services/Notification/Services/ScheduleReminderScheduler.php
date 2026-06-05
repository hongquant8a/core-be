<?php

namespace App\Services\Notification\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\SessionType;
use Carbon\Carbon;

class ScheduleReminderScheduler
{
    /**
     * Tạo pending reminders cho schedule khi được publish.
     * Pattern giống TaskAssignment: tính remind_at, lưu DB, cron sẽ poll và fire.
     */
    public function scheduleFor(Schedule $schedule): void
    {
        $eventDatetime = $this->getEventDatetime($schedule);
        $schedule->load('reminders');

        foreach ($schedule->reminders as $reminder) {
            $remindAt = $this->computeRemindAt($eventDatetime, $reminder->moment, (int) $reminder->offset_minutes);

            if ($remindAt === null || !$remindAt->isFuture()) {
                // Đã qua hoặc không tính được — fire ngay khi cron chạy
                $reminder->update([
                    'remind_at' => now(),
                    'status'    => 'pending',
                ]);
            } else {
                $reminder->update([
                    'remind_at' => $remindAt,
                    'status'    => 'pending',
                ]);
            }
        }
    }

    /**
     * Hủy pending reminders của schedule.
     */
    public function cancelPending(Schedule $schedule): void
    {
        $schedule->reminders()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Tính remind_at từ event time + moment + offset_minutes.
     */
    public function computeRemindAt(Carbon $eventTime, string $moment, int $offsetMinutes): ?Carbon
    {
        return match ($moment) {
            'immediate' => now(),
            'before'    => $offsetMinutes ? $eventTime->copy()->subMinutes($offsetMinutes) : $eventTime,
            'on'        => $eventTime->copy(),
            'after'     => $offsetMinutes ? $eventTime->copy()->addMinutes($offsetMinutes) : null,
            default     => null,
        };
    }

    /**
     * Lấy thời gian sự kiện từ schedule.
     */
    public function getEventDatetime(Schedule $schedule): Carbon
    {
        $dateTime = $schedule->date_time;
        if ($dateTime instanceof Carbon) {
            return $dateTime;
        }

        $dateStr = Carbon::parse($dateTime)->format('Y-m-d');
        $sessionVal = $schedule->session instanceof SessionType
            ? $schedule->session->value
            : $schedule->session;

        $timeStr = match ($sessionVal) {
            'S' => '07:30:00',
            'C' => '13:30:00',
            'T' => '19:30:00',
            default => '08:00:00',
        };

        return Carbon::parse("{$dateStr} {$timeStr}");
    }

    /**
     * Lấy danh sách User nhận reminder từ recipients của schedule.
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
