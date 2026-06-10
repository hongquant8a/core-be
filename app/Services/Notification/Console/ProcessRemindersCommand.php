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

        // Gửi reminder cho khách mời (guest) — không có user account, dùng contact info trực tiếp.
        $this->dispatchGuestReminders($meeting, $channels, $reminder->moment);

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }

    /**
     * Gửi reminder đến khách mời (guest) — không cần User account.
     * Dùng NotificationService::send() trực tiếp thay vì NotificationDispatcher (vốn yêu cầu User).
     */
    private function dispatchGuestReminders(\App\Modules\Meeting\Models\Meeting $meeting, array $channels, string $moment): void
    {
        $guests = $meeting->guests()->get();
        if ($guests->isEmpty()) {
            return;
        }

        $notifier = app(\App\Services\Notification\NotificationService::class);

        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $url = rtrim((string) config('app.frontend_url'), '/') . "/meetings/{$meeting->id}";

        $subject = match ($moment) {
            'before' => "Nhắc cuộc họp sắp diễn ra: {$meeting->title}",
            'on'     => "Cuộc họp đã đến giờ: {$meeting->title}",
            'after'  => "Cuộc họp đã kết thúc: {$meeting->title}",
            default  => "Nhắc cuộc họp: {$meeting->title}",
        };

        foreach ($guests as $guest) {
            foreach ($channels as $channel) {
                match ($channel) {
                    'mail' => $this->sendGuestMail($notifier, $guest, $meeting, $subject, $moment),
                    'sms'  => $this->sendGuestSms($notifier, $guest, $meeting, $start, $url, $moment),
                    'zalo' => $this->sendGuestZalo($notifier, $guest, $meeting, $start, $url, $moment),
                    default => null,
                };
            }
        }
    }

    private function sendGuestMail($notifier, $guest, $meeting, string $subject, string $moment): void
    {
        if (empty($guest->email)) {
            return;
        }

        $view = view("notifications.meeting_reminder_{$moment}.email", [
            'recipient' => $guest,
            'meeting'   => $meeting,
            'url'       => rtrim((string) config('app.frontend_url'), '/') . "/meetings/{$meeting->id}",
        ])->render();

        $notifier->send(new \App\Services\Notification\DTOs\NotificationPayload(
            channels: ['mail'],
            recipient: new \App\Services\Notification\DTOs\Recipient(
                email: $guest->email,
                name: $guest->name,
            ),
            content: $view,
            subject: $subject,
        ));
    }

    private function sendGuestSms($notifier, $guest, $meeting, string $start, string $url, string $moment): void
    {
        if (empty($guest->phone)) {
            return;
        }

        $text = match ($moment) {
            'before' => "Sap hop: {$meeting->title} ({$start}). Xem: {$url}",
            'on'     => "Den gio hop: {$meeting->title}. Xem: {$url}",
            'after'  => "Cuoc hop {$meeting->title} da ket thuc. Xem: {$url}",
            default  => "Nhac hop: {$meeting->title}. Xem: {$url}",
        };

        $notifier->send(new \App\Services\Notification\DTOs\NotificationPayload(
            channels: ['sms'],
            recipient: new \App\Services\Notification\DTOs\Recipient(
                phone: $guest->phone,
                name: $guest->name,
            ),
            content: \Illuminate\Support\Str::ascii($text),
        ));
    }

    private function sendGuestZalo($notifier, $guest, $meeting, string $start, string $url, string $moment): void
    {
        if (empty($guest->zalo_user_id)) {
            return;
        }

        $prefix = match ($moment) {
            'before' => 'Nhắc cuộc họp sắp diễn ra',
            'on'     => 'Cuộc họp đã đến giờ',
            'after'  => 'Cuộc họp đã kết thúc',
            default  => 'Nhắc lịch họp',
        };
        $text = "{$prefix}: {$meeting->title}.".($start ? " Thời gian: {$start}." : '')." Xem chi tiết: {$url}";

        $notifier->send(new \App\Services\Notification\DTOs\NotificationPayload(
            channels: ['zalo'],
            recipient: new \App\Services\Notification\DTOs\Recipient(
                zaloId: $guest->zalo_user_id,
                name: $guest->name,
            ),
            content: $text,
            context: [
                'customer_name' => $guest->name,
                'meeting_title' => $meeting->title,
                'url' => $url,
            ],
        ));
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
