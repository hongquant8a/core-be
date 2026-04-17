<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
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
        $config = NotificationEventConfig::global()
            ->where('event_key', 'task_confirmed')
            ->first();
        if (! $config || ! $config->enabled || empty($config->channels)) {
            return;
        }

        $item = $event->item->load('users');
        $builder = $this->registry->for('task_confirmed');

        foreach ($item->users as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'task_confirmed',
                recipient: $user,
                notifiable: $item,
                channels: $config->channels,
                builder: $builder,
            );
        }
    }
}
