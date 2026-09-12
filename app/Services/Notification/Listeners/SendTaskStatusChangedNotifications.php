<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\TaskStatusChanged;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTaskStatusChangedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(TaskStatusChanged $event): void
    {
        $item = $event->item->load(['users', 'document']);
        $organizationId = (int) ($item->organization_id ?: $item->document?->organization_id);
        if (! $organizationId) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        $recipients = $item->users;

        foreach ($recipients as $recipient) {
            $this->dispatcher->dispatch(
                eventKey: 'task_status_changed',
                recipient: $recipient,
                notifiable: $item,
                channels: $channels,
                builder: $this->registry->for('task_status_changed'),
                organizationId: $organizationId,
                extraArgs: [$event->previousStatus],
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'task_status_changed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
