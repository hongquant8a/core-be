<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\TaskConfirmed;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskConfirmedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(TaskConfirmed $event): void
    {
        $channels = $this->resolveChannels();
        if (empty($channels)) {
            return;
        }

        $item = $event->item->load('users');
        $builder = $this->registry->for('task_confirmed');

        foreach ($item->users as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'task_confirmed',
                recipient: $user,
                notifiable: $item,
                channels: $channels,
                builder: $builder,
            );
        }
    }

    private function resolveChannels(): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('event_key', 'task_confirmed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
