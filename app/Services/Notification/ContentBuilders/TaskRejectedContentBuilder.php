<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskRejectedContentBuilder implements ContentBuilder
{
    use BuildZns;

    /** Lý do từ chối được truyền qua $extraArgs[0] (xem SendTaskRejectedNotifications). */
    private function reasonFrom(array $extraArgs): string
    {
        return isset($extraArgs[0]) && is_string($extraArgs[0]) ? $extraArgs[0] : '';
    }

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return null;
        }
        $reason = $this->reasonFrom($extraArgs);

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable, $reason),
            'mail' => $this->toMail($recipient, $notifiable, $reason),
            'zalo' => $this->toZalo($recipient, $notifiable, $reason),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable, $reason),
            'telegram' => $this->toTelegram($recipient, $notifiable, $reason),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Công việc bị trả lại';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            $reason = $this->reasonFrom($extraArgs);
            $suffix = $reason !== '' ? " Lý do: {$reason}" : '';

            return "Công việc \"{$notifiable->name}\" bị trả lại, cần thực hiện lại.{$suffix}";
        }

        return 'Có công việc bị trả lại.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return [
                'url' => "/task-assignment-items/{$notifiable->id}",
                'reason' => $this->reasonFrom($extraArgs),
            ];
        }

        return [];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $notifiable->name,
            'deadline' => $notifiable->end_at?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Công việc bị trả lại',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'task_name' => 'Tên công việc',
            'deadline' => 'Thời hạn',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã công việc',
        ];
    }

    private function toSms(User $recipient, TaskAssignmentItem $item, string $reason): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $text = "Cong viec '{$item->name}' bi tra lai, can thuc hien lai. Tran trong !";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, TaskAssignmentItem $item, string $reason): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.task_rejected.email', [
            'recipient' => $recipient,
            'item' => $item,
            'reason' => $reason,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Công việc bị trả lại: {$item->name}",
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $item, string $reason): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $suffix = $reason !== '' ? " Lý do: {$reason}" : '';
        $text = "Công việc bị trả lại: {$item->name}.{$suffix}";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'event' => 'Công việc bị trả lại',
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItem $item, string $reason): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "'{$item->name}' bị trả lại, cần thực hiện lại.",
            subject: 'Công việc bị trả lại',
            context: [
                'url' => "/task-assignment-items/{$item->id}",
                'type' => 'task_rejected',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItem $item, string $reason): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $suffix = $reason !== '' ? "\n\nLý do: {$reason}" : '';
        $text = "<b>Công việc bị trả lại</b>\n\n{$item->name} — cần thực hiện lại.{$suffix}";

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
