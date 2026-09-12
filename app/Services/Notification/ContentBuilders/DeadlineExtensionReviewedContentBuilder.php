<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskExtensionStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Dùng chung cho cả duyệt lẫn từ chối — phân nhánh theo `$extension->status`.
 * Xem chú thích ở Events\DeadlineExtensionReviewed để biết vì sao gộp một sự kiện.
 */
class DeadlineExtensionReviewedContentBuilder implements ContentBuilder
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

    private function isApproved(TaskAssignmentItemExtension $ext): bool
    {
        return $ext->status === TaskExtensionStatusEnum::Approved->value;
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof TaskAssignmentItemExtension && ! $this->isApproved($notifiable)) {
            return 'Yêu cầu gia hạn bị từ chối';
        }

        return 'Yêu cầu gia hạn đã được duyệt';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return 'Yêu cầu gia hạn của bạn đã được xử lý.';
        }

        $name = $notifiable->item?->name;
        $note = $notifiable->review_note ? ' Ghi chú: '.$notifiable->review_note : '';

        if ($this->isApproved($notifiable)) {
            return sprintf('Yêu cầu gia hạn công việc "%s" đã được duyệt. Thời hạn mới: %s.%s',
                $name, $this->fmt($notifiable->requested_end_at), $note);
        }

        return sprintf('Yêu cầu gia hạn công việc "%s" bị từ chối. Thời hạn giữ nguyên: %s.%s',
            $name, $this->fmt($notifiable->current_end_at), $note);
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if (! $notifiable instanceof TaskAssignmentItemExtension) {
            return [];
        }

        return [
            'url' => "/task-assignment-items/{$notifiable->task_assignment_item_id}",
            'extension_id' => $notifiable->id,
            'status' => $notifiable->status,
            'review_note' => $notifiable->review_note,
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
            'deadline' => $this->isApproved($notifiable)
                ? $this->fmt($notifiable->requested_end_at)
                : $this->fmt($notifiable->current_end_at),
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
            'deadline' => 'Thời hạn sau khi xử lý',
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
        $text = $this->isApproved($ext)
            ? "Yeu cau gia han cong viec '{$ext->item?->name}' da duoc duyet. Tran trong !"
            : "Yeu cau gia han cong viec '{$ext->item?->name}' bi tu choi. Tran trong !";

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
        $html = view('notifications.deadline_extension_reviewed.email', [
            'recipient' => $recipient,
            'extension' => $ext,
            'item' => $ext->item,
            'approved' => $this->isApproved($ext),
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: $this->title($recipient, $ext).": {$ext->item?->name}",
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
                'event' => $this->title($recipient, $ext),
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
            content: $this->shortBody($recipient, $ext),
            subject: $this->title($recipient, $ext),
            context: [
                'url' => "/task-assignment-items/{$ext->task_assignment_item_id}",
                'type' => 'deadline_extension_reviewed',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItemExtension $ext): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $text = '<b>'.$this->title($recipient, $ext)."</b>\n\n".$this->shortBody($recipient, $ext);

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
