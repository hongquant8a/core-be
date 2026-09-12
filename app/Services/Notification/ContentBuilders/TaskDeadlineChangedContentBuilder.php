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

/** Thời hạn công việc bị người quản lý sửa trực tiếp — `$extraArgs[0]` là hạn cũ. */
class TaskDeadlineChangedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return null;
        }

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable, ...$extraArgs),
            'mail' => $this->toMail($recipient, $notifiable, ...$extraArgs),
            'zalo' => $this->toZalo($recipient, $notifiable, ...$extraArgs),
            'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable, ...$extraArgs),
            'telegram' => $this->toTelegram($recipient, $notifiable, ...$extraArgs),
            default => null,
        };
    }

    private function previousFrom(array $extraArgs): string
    {
        return isset($extraArgs[0]) && is_string($extraArgs[0]) ? $extraArgs[0] : '(không rõ)';
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Thời hạn công việc thay đổi';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return 'Thời hạn một công việc đã thay đổi.';
        }

        return sprintf('Thời hạn công việc "%s" đã đổi từ %s sang %s.',
            $notifiable->name,
            $this->previousFrom($extraArgs),
            $notifiable->end_at?->format('H:i d/m/Y') ?? '(không có)');
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return [];
        }

        return [
            'url' => "/task-assignment-items/{$notifiable->id}",
        ];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $model->name,
            'deadline' => $notifiable->end_at?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => $this->title($recipient, $notifiable, ...$extraArgs),
            'title' => $this->title($recipient, $notifiable, ...$extraArgs),
        ];
    }

    public function znsVariables(): array
    {
        return [
            'customer_name' => 'Tên người nhận',
            'gender' => 'Giới tính',
            'task_name' => 'Tên công việc',
            'deadline' => 'Thời hạn mới',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã bản ghi',
        ];
    }

    private function toSms(User $recipient, TaskAssignmentItem $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }

        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: Str::ascii($this->shortBody($recipient, $model, ...$extraArgs).' Tran trong !'),
        );
    }

    private function toMail(User $recipient, TaskAssignmentItem $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.task_deadline_changed.email', [
            'recipient' => $recipient,
            'model' => $model,
            'summary' => $this->shortBody($recipient, $model, ...$extraArgs),
            'title' => $this->title($recipient, $model, ...$extraArgs),
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $this->title($recipient, $model, ...$extraArgs).': '.$model->name,
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $this->shortBody($recipient, $model, ...$extraArgs),
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $model->name,
                'event' => $this->title($recipient, $model, ...$extraArgs),
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItem $model, mixed ...$extraArgs): ?NotificationPayload
    {
        $tokens = $recipient->fcmTokens()->pluck('fcm_token')->all();
        if (empty($tokens)) {
            return null;
        }

        return new NotificationPayload(
            channels: ['fcm'],
            recipient: new Recipient(fcmTokens: $tokens),
            content: $this->shortBody($recipient, $model, ...$extraArgs),
            subject: $this->title($recipient, $model, ...$extraArgs),
            context: [
                'url' => "/task-assignment-items/{$model->id}",
                'type' => 'task_deadline_changed',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItem $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: '<b>'.$this->title($recipient, $model, ...$extraArgs)."</b>\n\n".$this->shortBody($recipient, $model, ...$extraArgs),
        );
    }
}
