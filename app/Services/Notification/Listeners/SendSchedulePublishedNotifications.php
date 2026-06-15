<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\ScheduleReminderScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSchedulePublishedNotifications implements ShouldQueue
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected ContentBuilderRegistry $registry,
        protected ScheduleReminderScheduler $scheduler
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

        $recipients = $this->scheduler->resolveRecipients($schedule);
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
        $instantChannels = $schedule->reminders()
            ->where('reminder_type', 'instant')
            ->where('source', 'CUSTOM')
            ->where('status', 'active')
            ->value('channels');
        if (! empty($instantChannels)) {
            return $instantChannels;
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
