<?php

namespace App\Services\Notification\Console;

use App\Models\Reminder;
use App\Services\Notification\Contracts\Remindable;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessRemindersCommand extends Command
{
    protected $signature   = 'notifications:process-reminders';
    protected $description = 'Fire pending reminders across all modules';

    public function handle(
        NotificationDispatcher $dispatcher,
        ContentBuilderRegistry $registry,
        NotificationService $notifier,
    ): int {
        $count = 0;

        Reminder::with(['remindable', 'notificationSchedule.eventConfig'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunkById(100, function (Collection $reminders) use ($dispatcher, $registry, $notifier, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fire($reminder, $dispatcher, $registry, $notifier);
                    $count++;
                }
            });

        $this->info("Processed {$count} reminders.");
        return self::SUCCESS;
    }

    private function fire(
        Reminder $reminder, 
        NotificationDispatcher $dispatcher, 
        ContentBuilderRegistry $registry, 
        NotificationService $notifier
    ): void {
        $remindable = $reminder->remindable;

        // Validate
        if (!$remindable instanceof Remindable || !$remindable->isValidForReminder()) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        // Resolve channels
        $channels = $this->resolveChannels($reminder);
        if (empty($channels)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);
            return;
        }

        $organizationId = $remindable->getReminderOrganizationId();
        $eventKey       = $remindable->getReminderEventKey($reminder->moment);
        $builder        = $registry->for($eventKey);

        // Gửi cho User recipients
        foreach ($remindable->resolveReminderRecipients() as $user) {
            $dispatcher->dispatch(
                eventKey:       $eventKey,
                recipient:      $user,
                notifiable:     $remindable,
                channels:       $channels,
                builder:        $builder,
                organizationId: $organizationId,
            );
        }

        // Gửi cho Guest (chỉ Meeting)
        foreach ($remindable->resolveGuestReminderRecipients() as $guest) {
            $this->sendToGuest($guest, $remindable, $channels, $reminder->moment, $notifier);
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }

    private function resolveChannels(Reminder $reminder): array
    {
        // Logic dùng chung — không cần 3 method riêng
        if ($reminder->source === 'CUSTOM' && !empty($reminder->channels)) {
            return array_map(fn($c) => strtolower(trim($c)), $reminder->channels);
        }

        $schedule = $reminder->notificationSchedule;
        $config   = $schedule?->eventConfig;

        if (!$config || !$config->enabled || empty($schedule->channels)) {
            return [];
        }

        return array_map(fn($c) => strtolower(trim($c)), $schedule->channels);
    }

    private function sendToGuest($guest, $meeting, array $channels, ?string $moment, NotificationService $notifier): void
    {
        if (!($meeting instanceof \App\Modules\Meeting\Models\Meeting)) {
            return;
        }

        $momentStr = $moment ?? 'scheduled';
        $start = $meeting->start_time?->format('d/m/Y H:i') ?? '';
        $url = rtrim((string) config('app.frontend_url'), '/') . "/meetings/{$meeting->id}";

        $subject = match ($momentStr) {
            'before' => "Nhắc cuộc họp sắp diễn ra: {$meeting->title}",
            'on'     => "Cuộc họp đã đến giờ: {$meeting->title}",
            'after'  => "Cuộc họp đã kết thúc: {$meeting->title}",
            default  => "Nhắc cuộc họp: {$meeting->title}",
        };

        foreach ($channels as $channel) {
            match ($channel) {
                'mail' => $this->sendGuestMail($notifier, $guest, $meeting, $subject, $momentStr),
                'sms'  => $this->sendGuestSms($notifier, $guest, $meeting, $start, $url, $momentStr),
                'zalo' => $this->sendGuestZalo($notifier, $guest, $meeting, $start, $url, $momentStr),
                default => null,
            };
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
}
