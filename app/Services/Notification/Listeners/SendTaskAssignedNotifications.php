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

        [$channels, $instantReminders] = $this->resolveChannels($item, $organizationId);
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

        // Mark instant CUSTOM reminders fired SAU khi dispatch xong.
        // Nếu không mark, cron sẽ bắn lại reminder với getReminderEventKey(null) = 'document_issued' — sai event.
        if ($instantReminders->isNotEmpty()) {
            $instantReminders->each(fn ($r) => $r->update(['status' => 'fired', 'fired_at' => now()]));
        }
    }

    private function resolveChannels(\App\Modules\TaskAssignment\Models\TaskAssignmentItem $item, int $organizationId): array
    {
        // Per-record: kiểm tra item.reminders có reminder_type=instant không.
        $instantReminders = $item->reminders()
            ->where('reminder_type', 'instant')
            ->where('status', 'active')
            ->get();

        if ($instantReminders->isNotEmpty()) {
            $channels = $instantReminders->first()->channels ?? [];
            if (! empty($channels)) {
                return [array_map('strtolower', $channels), $instantReminders];
            }
        }

        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'task_assigned')
            ->first();
        if (! $config || ! $config->enabled) {
            return [[], collect()];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return [$instant?->channels ?? [], collect()];
    }
}
