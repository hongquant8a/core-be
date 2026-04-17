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
        $item = $event->item->load(['assigner', 'document']);
        $manager = $item->assigner;
        if (! $manager) {
            return;
        }

        $organizationId = (int) $item->document->organization_id;
        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('task_completed');

        $this->dispatcher->dispatch(
            eventKey: 'task_completed',
            recipient: $manager,
            notifiable: $item,
            channels: $channels,
            builder: $builder,
            organizationId: $organizationId,
        );
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'task_completed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
