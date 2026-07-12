<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Services\NotificationDispatcher;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendScheduleCancelledNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        protected NotificationDispatcher $dispatcher,
        protected ContentBuilderRegistry $registry,
        protected ReminderScheduler $scheduler
    ) {}

    public function handle(ScheduleCancelled $event): void
    {
        $schedule = $event->schedule;
        $organizationId = (int) $schedule->organization_id;
        if ($organizationId === 0) {
            return;
        }

        // Luôn hủy pending reminders, bất kể có instant channels hay không.
        $this->scheduler->cancelPending($schedule);

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $recipients = $schedule->resolveReminderRecipients();
        $builder = $this->registry->for('schedule_cancelled');

        foreach ($recipients as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'schedule_cancelled',
                recipient: $user,
                notifiable: $schedule,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::Scheduling->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'schedule_cancelled')
            ->first();

        if (!$config || !$config->enabled) {
            return [];
        }

        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }

}
