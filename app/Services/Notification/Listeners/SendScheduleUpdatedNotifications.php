<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\ScheduleReminderScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendScheduleUpdatedNotifications implements ShouldQueue
{
    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected ContentBuilderRegistry $registry,
        protected ScheduleReminderScheduler $scheduler
    ) {}

    public function handle(ScheduleUpdated $event): void
    {
        $schedule = $event->schedule;
        $organizationId = (int) $schedule->organization_id;
        if ($organizationId === 0) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $recipients = $this->scheduler->resolveRecipients($schedule);
        $builder = $this->registry->for('schedule_updated');

        foreach ($recipients as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'schedule_updated',
                recipient: $user,
                notifiable: $schedule,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

        // Cancel previous reminders, and schedule new ones
        $this->scheduler->cancelPending($schedule);
        $this->scheduler->scheduleFor($schedule);
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Scheduling->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'schedule_updated')
            ->first();

        if (!$config || !$config->enabled) {
            return [];
        }

        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
