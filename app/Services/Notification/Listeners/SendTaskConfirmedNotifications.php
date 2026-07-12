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
        $item = $event->item->load(['users', 'document']);
        $organizationId = (int) $item->document->organization_id;

        [$channels, $instantReminders] = $this->resolveChannels($item, $organizationId);
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('task_confirmed');

        foreach ($item->users as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'task_confirmed',
                recipient: $user,
                notifiable: $item,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

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
            ->where('event_key', 'task_confirmed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [[], collect()];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return [$instant?->channels ?? [], collect()];
    }
}
