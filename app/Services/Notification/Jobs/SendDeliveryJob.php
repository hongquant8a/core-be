<?php

namespace App\Services\Notification\Jobs;

use App\Modules\Core\Models\NotificationDelivery;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId, public array $extraArgs = []) {}

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
        $notifiable = $notification->notifiable; // morphTo resolves actual model

        if (! $notifiable) {
            $delivery->update(['status' => 'failed', 'error_message' => 'Đối tượng thông báo không còn tồn tại.']);

            return;
        }

        $builder = $registry->for($notification->event_key);

        $payload = $builder->build($delivery->channel, $recipient, $notifiable, ...$this->extraArgs);

        if ($payload === null) {
            $delivery->update([
                'status' => 'skipped',
                'error_message' => 'Người nhận thiếu thông tin liên hệ cho kênh gửi này.',
            ]);

            return;
        }

        // Zalo ZNS: resolve template cho (module, event, channel)
        if ($delivery->channel === 'zalo_zns') {
            $payload = $this->applyZnsTemplate($notification->event_key, $notification->organization_id, $payload, $delivery);
            if ($payload === null) {
                return;
            }
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

    /**
     * Resolve ZNS template cho module và remap context.
     * Trả về null nếu không có template → skip delivery.
     */
    private function applyZnsTemplate(
        string $eventKey,
        ?int $organizationId,
        NotificationPayload $payload,
        NotificationDelivery $delivery,
    ): ?NotificationPayload {
        try {
            $eventEnum = NotificationEventEnum::from($eventKey);
            $moduleKey = $eventEnum->module()->value;
        } catch (\ValueError) {
            $delivery->update(['status' => 'skipped', 'error_message' => "Không xác định module cho event: {$eventKey}"]);

            return null;
        }

        $templateService = app(NotificationTemplateService::class);
        $template = $templateService->resolve(
            $organizationId ?? 0,
            $moduleKey,
            'zalo_zns',
        );

        if (! $template) {
            $delivery->update(['status' => 'skipped', 'error_message' => 'Chưa cấu hình ZNS template cho module này.']);

            return null;
        }

        $mappedContext = $templateService->applyMapping($template, $payload->context);

        return new NotificationPayload(
            channels: $payload->channels,
            recipient: $payload->recipient,
            content: $payload->content,
            subject: $payload->subject,
            context: $mappedContext + ['_template_id' => $template->template_id],
        );
    }
}
