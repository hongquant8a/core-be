<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\TaskAssigned;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskAssignedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $item = $event->item->loadMissing('document');
        $organizationId = (int) ($item->document->organization_id ?? 0);
        if ($organizationId === 0) {
            return;
        }

        $channels = $this->resolveChannels($item, $organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('task_assigned');

        $this->dispatcher->dispatch(
            eventKey: 'task_assigned',
            recipient: $event->user,
            notifiable: $item,
            channels: $channels,
            builder: $builder,
            organizationId: $organizationId,
        );
    }

    private function resolveChannels(\App\Modules\TaskAssignment\Models\TaskAssignmentItem $item, int $organizationId): array
    {
        // Per-record: kiểm tra item.document.instant_channels.
        $perRecord = $item->document?->instant_channels;
        if (! empty($perRecord)) {
            return $perRecord;
        }
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'task_assigned')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
