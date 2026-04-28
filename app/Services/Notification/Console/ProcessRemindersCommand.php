<?php

namespace App\Services\Notification\Console;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class ProcessRemindersCommand extends Command
{
    protected $signature = 'notifications:process-reminders';

    protected $description = 'Fire scheduled reminders due now (create notifications + deliveries)';

    public function handle(NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): int
    {
        $count = 0;

        TaskAssignmentReminder::with(['item.users', 'item.document', 'schedule'])
            ->where('status', 'pending')
            ->where('remind_at', '<=', now())
            ->chunkById(100, function ($reminders) use ($dispatcher, $registry, &$count) {
                foreach ($reminders as $reminder) {
                    $this->fireReminder($reminder, $dispatcher, $registry);
                    $count++;
                }
            });

        $this->info("Processed {$count} reminders.");

        return self::SUCCESS;
    }

    private function fireReminder(TaskAssignmentReminder $reminder, NotificationDispatcher $dispatcher, ContentBuilderRegistry $registry): void
    {
        $item = $reminder->item;
        if (! $item) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        // Skip nếu item đã done/cancelled
        if (in_array($item->processing_status, [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::Cancelled->value], true)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $organizationId = (int) ($item->document->organization_id ?? 0);
        if ($organizationId === 0) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $eventKey = "reminder_{$reminder->moment}";

        // Channels lấy từ chính schedule của reminder (schedule là child của event_config).
        // Check enabled của parent event_config trước — scope theo organization.
        $schedule = $reminder->schedule;
        $config = $schedule?->eventConfig;

        if (! $config || (int) $config->organization_id !== $organizationId || ! $config->enabled || empty($schedule->channels)) {
            $reminder->update(['status' => 'cancelled', 'fired_at' => now()]);

            return;
        }

        $channels = $schedule->channels;

        $builder = $registry->for($eventKey);

        foreach ($item->users as $user) {
            $dispatcher->dispatch(
                eventKey: $eventKey,
                recipient: $user,
                notifiable: $item,
                channels: $channels,
                builder: $builder,
                organizationId: $organizationId,
            );
        }

        $reminder->update(['status' => 'fired', 'fired_at' => now()]);
    }
}
