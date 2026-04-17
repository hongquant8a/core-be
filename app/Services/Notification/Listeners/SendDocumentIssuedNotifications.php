<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDocumentIssuedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(DocumentIssued $event): void
    {
        $channels = $this->resolveChannels();
        if (empty($channels)) {
            return;
        }

        $builder = $this->registry->for('document_issued');
        $items = $event->document->items()->with('users')->get();

        foreach ($items as $item) {
            foreach ($item->users as $user) {
                $this->dispatcher->dispatch(
                    eventKey: 'document_issued',
                    recipient: $user,
                    notifiable: $item,
                    channels: $channels,
                    builder: $builder,
                );
            }
        }
    }

    private function resolveChannels(): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('event_key', 'document_issued')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }

        // Non-reminder event: lấy channels từ schedule instant (duy nhất)
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
