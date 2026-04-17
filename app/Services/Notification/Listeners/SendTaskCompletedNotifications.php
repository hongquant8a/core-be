<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskCompletedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(TaskCompleted $event): void
    {
        $channels = $this->resolveChannels();
        if (empty($channels)) {
            return;
        }

        $item = $event->item->load('assigner');
        $manager = $item->assigner;
        if (! $manager) {
            return;
        }

        $builder = $this->registry->for('task_completed');

        $this->dispatcher->dispatch(
            eventKey: 'task_completed',
            recipient: $manager,
            notifiable: $item,
            channels: $channels,
            builder: $builder,
        );
    }

    private function resolveChannels(): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('event_key', 'task_completed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
