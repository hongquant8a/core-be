<?php

namespace App\Services\Notification\Console;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\MeetingReminder;
use App\Modules\Scheduling\Models\ScheduleReminder;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'notifications:process-reminders';

    protected $description = 'Fire scheduled reminders due now (create notifications + deliveries)';

    public function handle(NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): int
    {
        $count = 0;

        TaskAssignmentReminder::with(['item.users', 'item.document', 'schedule.eventConfig'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunkById(100, function ($reminders) use ($dispatcher, $registry, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fireReminder($reminder, $dispatcher, $registry);
                    $count++;
                }
            });

        MeetingReminder::with(['meeting', 'schedule.eventConfig'])
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('remind_at', '<=', now())
                  ->orWhere(fn ($q2) => $q2->whereNull('remind_at')->where('scheduled_at', '<=', now()));
            })
            ->chunkById(100, function ($reminders) use ($dispatcher, $registry, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fireMeetingReminder($reminder, $dispatcher, $registry);
                    $count++;
                }
            });

        ScheduleReminder::with(['schedule', 'notificationSchedule.eventConfig'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunkById(100, function ($reminders) use ($dispatcher, $registry, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fireScheduleReminder($reminder, $dispatcher, $registry);
                    $count++;
                }
            });

        $this->info("Processed {$count} reminders.");

        return self::SUCCESS;
    }

    private function fireMeetingReminder(MeetingReminder $reminder, NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): void
    {
        $meeting = $reminder->meeting;
        if (! $meeting) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        // Skip nếu meeting bị hủy (status=cancelled). Reminders sau end_time vẫn được fire (vd: nhắc "đã kết thúc" — moment=after).
        if ($meeting->status === 'cancelled') {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $organizationId = (int) $reminder->organization_id;

        // CUSTOM per-record: dùng channels từ chính reminder.
        if ($reminder->source === 'CUSTOM' && ! empty($reminder->channels)) {
            $channels = array_map(fn($c) => strtolower(trim($c)), $reminder->channels);
        } else {
            // PRESET: dùng channels từ notification_schedule config.
            $schedule = $reminder->schedule;
            $config = $schedule?->eventConfig;

            if (! $config || (int) $config->organization_id !== $organizationId || ! $config->enabled || empty($schedule->channels)) {
                $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

                return;
            }

            $channels = array_map(fn($c) => strtolower(trim($c)), $schedule->channels);
        }

        $eventKey = "meeting_reminder_{$reminder->moment}";
        $builder = $registry->for($eventKey);

        $userIds = $meeting->participants()
            ->with('attendee')
            ->get()
            ->pluck('attendee.user_id')
            ->filter()
            ->all();

        // Bổ sung chủ trì + thư ký (FK trên meetings, không nằm trong participants).
        $meeting->loadMissing(['chairperson', 'operator']);
        foreach ([$meeting->chairperson?->user_id, $meeting->operator?->user_id] as $extraUserId) {
            if ($extraUserId) {
                $userIds[] = $extraUserId;
            }
        }

        $userIds = collect($userIds)->filter()->unique()->values()->all();

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }
            $dispatcher->dispatch(
                eventKey: $eventKey,
                recipient: $user,
                notifiable: $meeting,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }

    private function fireReminder(TaskAssignmentReminder $reminder, NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): void
    {
        $item = $reminder->item;
        if (! $item) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        // Skip nếu item đã done/cancelled
        if (in_array($item->processing_status, [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value], true)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $organizationId = (int) ($item->document->organization_id ?? 0);
        if ($organizationId === 0) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $eventKey = "reminder_{$reminder->moment}";

        // CUSTOM per-record: dùng channels từ chính reminder.
        if ($reminder->source === 'CUSTOM' && ! empty($reminder->channels)) {
            $channels = array_map(fn($c) => strtolower(trim($c)), $reminder->channels);
        } else {
            // PRESET: dùng channels từ notification_schedule config.
            $schedule = $reminder->schedule;
            $config = $schedule?->eventConfig;

            if (! $config || (int) $config->organization_id !== $organizationId || ! $config->enabled || empty($schedule->channels)) {
                $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

                return;
            }

            $channels = array_map(fn($c) => strtolower(trim($c)), $schedule->channels);
        }

        $builder = $registry->for($eventKey);

        foreach ($item->users as $user) {
            $dispatcher->dispatch(
                eventKey: $eventKey,
                recipient: $user,
                notifiable: $item,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }

    private function fireScheduleReminder(ScheduleReminder $reminder, NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): void
    {
        $schedule = $reminder->schedule;
        if (! $schedule) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $statusVal = $schedule->status instanceof \App\Modules\Scheduling\Enums\ScheduleStatus
            ? $schedule->status->value
            : (int) $schedule->status;

        if ($statusVal !== \App\Modules\Scheduling\Enums\ScheduleStatus::PUBLISHED->value) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $organizationId = (int) $schedule->organization_id;
        if ($organizationId === 0) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        // CUSTOM per-record: dùng channels từ chính reminder.
        if ($reminder->source === 'CUSTOM' && ! empty($reminder->channels)) {
            $channels = array_map(fn($c) => strtolower(trim($c)), $reminder->channels);
        } else {
            // PRESET: dùng channels từ notification_schedule config.
            $notifSchedule = $reminder->notificationSchedule;
            $config = $notifSchedule?->eventConfig;

            if (! $config || ! $config->enabled) {
                $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
                return;
            }

            $channels = array_map(fn($c) => strtolower(trim($c)), $notifSchedule->channels ?? []);
        }

        if (empty($channels)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $builder = $registry->for('schedule_reminder');

        $schedule->loadMissing('recipients.user');
        foreach ($schedule->recipients as $recipient) {
            $user = $recipient->user;
            if (! $user) {
                continue;
            }

            $dispatcher->dispatch(
                eventKey: 'schedule_reminder',
                recipient: $user,
                notifiable: $schedule,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }
}
