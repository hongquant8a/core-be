<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    /**
     * Create notification + deliveries + dispatch jobs.
     *
     * @param  array<string>  $channels
     * @param  array<mixed>  $builderArgs  args passed into builder methods
     */
    public function dispatch(
        string $eventKey,
        User $recipient,
        Model $notifiable,
        array $channels,
        ContentBuilder $builder,
        array $builderArgs = [],
    ): Notification {
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'event_key' => $eventKey,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'title' => $builder->title($recipient, ...$builderArgs),
            'body' => $builder->shortBody($recipient, ...$builderArgs),
            'context' => $builder->inAppContext($recipient, ...$builderArgs),
        ]);

        foreach ($channels as $channelKey) {
            $delivery = NotificationDelivery::create([
                'notification_id' => $notification->id,
                'channel' => $channelKey,
                'status' => 'pending',
            ]);
            SendDeliveryJob::dispatch($delivery->id, $builderArgs)->onQueue('notifications');
        }

        return $notification;
    }
}
