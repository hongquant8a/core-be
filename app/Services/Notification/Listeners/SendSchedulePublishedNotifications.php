<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSchedulePublishedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected ContentBuilderRegistry $registry,
        protected ReminderScheduler $scheduler
    ) {}

    public function handle(SchedulePublished $event): void
    {
        $schedule = $event->schedule;
        $organizationId = (int) $schedule->organization_id;
        if ($organizationId === 0) {
            return;
        }

        // Luôn schedule reminders — remind_at phải được set bất kể có instant channels hay không.
        $this->scheduler->scheduleFor($schedule);

        $channels = $this->resolveChannels($organizationId, $schedule);
        if (empty($channels)) {
            return;
        }

        $recipients = $schedule->resolveReminderRecipients();
        $builder = $this->registry->for('schedule_published');

        foreach ($recipients as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'schedule_published',
                recipient: $user,
                notifiable: $schedule,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }
    }

    private function resolveChannels(int $organizationId, \App\Modules\Scheduling\Models\Schedule $schedule): array
    {
        // Per-record: kiểm tra schedule.reminders có reminder_type=instant (active).
        $instantReminders = $schedule->reminders()
            ->where('reminder_type', 'instant')
            ->where('source', 'CUSTOM')
            ->where('status', 'active')
            ->get();

        if ($instantReminders->isNotEmpty()) {
            // Lấy channels từ reminder đầu tiên (merge nếu cần).
            $channels = $instantReminders->first()->channels ?? [];
            // Mark tất cả instant reminders là fired để cron không fire lại.
            $instantReminders->each(fn ($r) => $r->update(['status' => 'fired', 'fired_at' => now()]));
            if (! empty($channels)) {
                return $channels;
            }
        }
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Scheduling->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'schedule_published')
            ->first();

        if (!$config || !$config->enabled) {
            return [];
        }

        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
