<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Đơn thư mới gửi tới phòng ban — báo cho người đại diện phòng ban đó. */
class PetitionCreatedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentPetition) {
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
        return 'Có đơn thư mới';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentPetition) {
            return 'Có đơn thư mới cần xử lý.';
        }

        return sprintf('Đơn thư của %s gửi %s, hạn xử lý %s.',
            $notifiable->sender_name ?: '(không rõ người gửi)',
            $notifiable->department?->name ?? 'phòng ban của bạn',
            $notifiable->deadline_date?->format('d/m/Y') ?? '(chưa đặt)');
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentPetition) {
            return [];
        }

        return [
            'url' => "/task-assignment-petitions/{$notifiable->id}",
        ];
    }

    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentPetition) {
            return [];
        }

        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $model->sender_name ?: 'Đơn thư',
            'deadline' => $notifiable->deadline_date?->format('d/m/Y') ?? '',
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
            'task_name' => 'Người gửi đơn',
            'deadline' => 'Hạn xử lý',
            'event' => 'Loại sự kiện',
            'code_id' => 'Mã bản ghi',
        ];
    }

    private function toSms(User $recipient, TaskAssignmentPetition $model, mixed ...$extraArgs): ?NotificationPayload
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

    private function toMail(User $recipient, TaskAssignmentPetition $model, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $recipient->email) {
            return null;
        }
        $html = view('notifications.petition_created.email', [
            'recipient' => $recipient,
            'model' => $model,
            'summary' => $this->shortBody($recipient, $model, ...$extraArgs),
            'title' => $this->title($recipient, $model, ...$extraArgs),
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $this->title($recipient, $model, ...$extraArgs).': '.$model->sender_name ?: 'Đơn thư',
        );
    }

    private function toZalo(User $recipient, TaskAssignmentPetition $model, mixed ...$extraArgs): ?NotificationPayload
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
                'task_name' => $model->sender_name ?: 'Đơn thư',
                'event' => $this->title($recipient, $model, ...$extraArgs),
            ],
        );
    }

    private function toFcm(User $recipient, TaskAssignmentPetition $model, mixed ...$extraArgs): ?NotificationPayload
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
                'url' => "/task-assignment-petitions/{$model->id}",
                'type' => 'petition_created',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentPetition $model, mixed ...$extraArgs): ?NotificationPayload
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
