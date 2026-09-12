<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Văn bản giao việc bị sửa sau khi đã ban hành. */
class DocumentUpdatedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentDocument) {
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

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Văn bản giao việc được cập nhật';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentDocument) {
            return 'Một văn bản giao việc đã được cập nhật.';
        }

        return sprintf('Văn bản "%s" đã được sửa sau khi ban hành. Đề nghị xem lại nội dung chỉ đạo.',
            Str::limit((string) $notifiable->name, 160));
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentDocument) {
            return [];
        }

        return [
            'url' => "/task-assignment-documents/{$notifiable->id}",
        ];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentDocument) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => Str::limit((string) $model->name, 120),
            'deadline' => $notifiable->issue_date?->format('d/m/Y') ?? '',
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
            'task_name' => 'Tên văn bản',
            'deadline' => 'Ngày ban hành',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã bản ghi',
        ];
    }

    private function toSms(User $recipient, TaskAssignmentDocument $model, mixed ...$extraArgs): ?NotificationPayload
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

    private function toMail(User $recipient, TaskAssignmentDocument $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.document_updated.email', [
            'recipient' => $recipient,
            'model' => $model,
            'summary' => $this->shortBody($recipient, $model, ...$extraArgs),
            'title' => $this->title($recipient, $model, ...$extraArgs),
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $this->title($recipient, $model, ...$extraArgs).': '.Str::limit((string) $model->name, 120),
        );
    }

    private function toZalo(User $recipient, TaskAssignmentDocument $model, mixed ...$extraArgs): ?NotificationPayload
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
                'task_name' => Str::limit((string) $model->name, 120),
                'event' => $this->title($recipient, $model, ...$extraArgs),
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentDocument $model, mixed ...$extraArgs): ?NotificationPayload
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
                'url' => "/task-assignment-documents/{$model->id}",
                'type' => 'document_updated',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentDocument $model, mixed ...$extraArgs): ?NotificationPayload
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
