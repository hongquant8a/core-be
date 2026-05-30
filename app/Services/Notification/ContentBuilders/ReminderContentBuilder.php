<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReminderContentBuilder implements ContentBuilder
{
    /**
     * @param  string  $moment  one of: 'before', 'on', 'after'
     */
    public function __construct(private string $moment) {}

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return match ($this->moment) {
            'before' => 'Nhắc công việc sắp đến hạn',
            'on' => 'Công việc đã đến hạn',
            'after' => 'Công việc đã quá hạn',
            default => 'Nhắc lịch công việc',
        };
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return 'Nhắc công việc.';
        }
        $deadline = $notifiable->end_at?->format('H:i d/m/Y') ?? '';

        return match ($this->moment) {
            'before' => "\"{$notifiable->name}\" sắp đến hạn {$deadline}.",
            'on' => "\"{$notifiable->name}\" đã đến hạn ({$deadline}).",
            'after' => "\"{$notifiable->name}\" đã quá hạn (hạn {$deadline}).",
            default => $notifiable->name,
        };
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return [
                'url' => "/task-assignment-items/{$notifiable->id}",
                'moment' => $this->moment,
            ];
        }

        return ['moment' => $this->moment];
    }

    private function toSms(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $deadline = $item->end_at?->format('d/m/Y H:i') ?? '';
        $text = match ($this->moment) {
            'before' => "Sap den han: {$item->name} ({$deadline}). Tran trong !",
            'on' => "Den han: {$item->name}. Tran trong !",
            'after' => "Qua han: {$item->name} (han {$deadline}). Tran trong !",
            default => "Nhac: {$item->name}. Tran trong !",
        };

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view("notifications.reminder_{$this->moment}.email", [
            'recipient' => $recipient,
            'item' => $item,
        ])->render();
        $subject = match ($this->moment) {
            'before' => "Nhắc: sắp đến hạn - {$item->name}",
            'on' => "Nhắc: đến hạn - {$item->name}",
            'after' => "Nhắc: quá hạn - {$item->name}",
            default => "Nhắc: {$item->name}",
        };

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $subject,
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $deadline = $item->end_at ? " (hạn {$item->end_at->format('d/m/Y H:i')})" : '';
        $prefix = match ($this->moment) {
            'before' => 'Sắp đến hạn công việc',
            'on' => 'Đến hạn công việc',
            'after' => 'Quá hạn công việc',
            default => 'Nhắc công việc',
        };
        $text = "{$prefix}: {$item->name}{$deadline}.";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'deadline' => $item->end_at?->format('d/m/Y H:i') ?? '',
                'moment' => $this->moment,
            ],
        );
    }

private function toZaloZns(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }

        $deadline = $item->end_at ? " (hạn {$item->end_at->format('d/m/Y H:i')})" : '';
        $prefix = match ($this->moment) {
            'before' => 'Sắp đến hạn công việc',
            'on' => 'Đến hạn công việc',
            'after' => 'Quá hạn công việc',
            default => 'Nhắc công việc',
        };
        $text = "{$prefix}: {$item->name}{$deadline}.";

        return new NotificationPayload(
            channels: ['zalo_zns'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'deadline' => $item->end_at?->format('d/m/Y H:i') ?? '',
                'moment' => $this->moment,
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: $this->shortBody($recipient, $item),
            subject: $this->title($recipient, $item),
            context: [
                'url' => "/task-assignment-items/{$item->id}",
                'type' => "reminder_{$this->moment}",
            ],
        );
    }
}
