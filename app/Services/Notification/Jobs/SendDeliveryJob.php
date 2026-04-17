<?php

namespace App\Services\Notification\Jobs;

use App\Modules\Core\Models\NotificationDelivery;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId, public array $builderArgs = []) {}

    public function handle(
        ContentBuilderRegistry $registry,
        NotificationService $notifier,
    ): void {
        $delivery = NotificationDelivery::with('notification.user')->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending') {
            return;
        }

        $notification = $delivery->notification;
        $recipient = $notification->user;

        $builder = $registry->for($notification->event_key);

        $payload = $builder->build($delivery->channel, $recipient, ...$this->builderArgs);

        if ($payload === null) {
            $delivery->update([
                'status' => 'skipped',
                'error_message' => 'Recipient missing field for channel',
            ]);

            return;
        }

        $results = $notifier->send($payload);
        $result = $results[0];

        $delivery->update([
            'status' => $result->success ? 'sent' : 'failed',
            'message_id' => $result->messageId,
            'error_message' => $result->error,
            'sent_at' => $result->success ? now() : null,
        ]);
    }
}
