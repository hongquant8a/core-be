<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeadlineExtensionRequestedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            'telegram' => $this->toTelegram($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Có yêu cầu gia hạn thời hạn';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return 'Có yêu cầu gia hạn thời hạn công việc.';
        }

        $who = $notifiable->requestedBy?->name ?? 'Người thực hiện';

        return sprintf(
            '%s xin dời hạn công việc "%s" từ %s sang %s. Lý do: %s',
            $who,
            $notifiable->item?->name,
            $this->fmt($notifiable->current_end_at),
            $this->fmt($notifiable->requested_end_at),
            $notifiable->reason,
        );
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return [];
        }

        return [
            'url' => "/task-assignment-items/{$notifiable->task_assignment_item_id}",
            'extension_id' => $notifiable->id,
            'reason' => $notifiable->reason,
        ];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $notifiable->item?->name ?? '',
            'deadline' => $this->fmt($notifiable->requested_end_at),
            'code_id' => (string) $notifiable->task_assignment_item_id,
            'event' => 'Có yêu cầu gia hạn thời hạn',
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'task_name' => 'Tên công việc',
            'deadline' => 'Thời hạn xin dời tới',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã công việc',
        ];
    }

    private function fmt($value): string
    {
        return $value?->format('H:i d/m/Y') ?? '(chưa có)';
    }

    private function toSms(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $text = "Co yeu cau gia han cong viec '{$ext->item?->name}'. De nghi vao he thong duyet. Tran trong !";

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.deadline_extension_requested.email', [
            'recipient' => $recipient,
            'extension' => $ext,
            'item' => $ext->item,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Yêu cầu gia hạn thời hạn: {$ext->item?->name}",
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $this->shortBody($recipient, $ext),
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $ext->item?->name ?? '',
                'event' => 'Có yêu cầu gia hạn thời hạn',
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: "'{$ext->item?->name}' có yêu cầu gia hạn tới {$this->fmt($ext->requested_end_at)}.",
            subject: 'Có yêu cầu gia hạn thời hạn',
            context: [
                'url' => "/task-assignment-items/{$ext->task_assignment_item_id}",
                'type' => 'deadline_extension_requested',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $text = "<b>Có yêu cầu gia hạn thời hạn</b>\n\n{$ext->item?->name}\n"
            ."Dời từ {$this->fmt($ext->current_end_at)} sang {$this->fmt($ext->requested_end_at)}\n\n"
            ."Lý do: {$ext->reason}";

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
