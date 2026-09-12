<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Trao đổi mới trên công việc. Nội dung ghi chú có thể chứa HTML nên phải lọc thẻ. */
class NoteAddedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItemNote) {
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

    private function plainContent(TaskAssignmentItemNote $note): string
    {
        return Str::limit(trim(strip_tags((string) $note->content)), 160);
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Có trao đổi mới trên công việc';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentItemNote) {
            return 'Có trao đổi mới trên một công việc.';
        }

        return sprintf('%s đã trao đổi trên công việc "%s": %s',
            $notifiable->author?->name ?? 'Một thành viên',
            $notifiable->item?->name,
            $this->plainContent($notifiable));
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItemNote) {
            return [];
        }

        return [
            'url' => "/task-assignment-items/{$notifiable->task_assignment_item_id}",
        ];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItemNote) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $model->item?->name ?? '',
            'deadline' => $notifiable->item?->end_at?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->task_assignment_item_id,
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
            'deadline' => 'Thời hạn',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã bản ghi',
        ];
    }

    private function toSms(User $recipient, TaskAssignmentItemNote $model, mixed ...$extraArgs): ?NotificationPayload
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

    private function toMail(User $recipient, TaskAssignmentItemNote $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.note_added.email', [
            'recipient' => $recipient,
            'model' => $model,
            'summary' => $this->shortBody($recipient, $model, ...$extraArgs),
            'title' => $this->title($recipient, $model, ...$extraArgs),
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $this->title($recipient, $model, ...$extraArgs).': '.$model->item?->name ?? '',
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItemNote $model, mixed ...$extraArgs): ?NotificationPayload
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
                'task_name' => $model->item?->name ?? '',
                'event' => $this->title($recipient, $model, ...$extraArgs),
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentItemNote $model, mixed ...$extraArgs): ?NotificationPayload
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
                'url' => "/task-assignment-items/{$model->task_assignment_item_id}",
                'type' => 'note_added',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItemNote $model, mixed ...$extraArgs): ?NotificationPayload
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
