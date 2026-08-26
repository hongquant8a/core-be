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

class ReportSubmittedContentBuilder implements ContentBuilder
{
    use BuildZns;

    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof TaskAssignmentItem) {
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
        return 'Có báo cáo công việc mới';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return "Công việc \"{$notifiable->name}\" vừa được nộp báo cáo, chờ bạn xem.";
        }

        return 'Có báo cáo công việc mới cần xem.';
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return [
                'url' => "/task-assignment-items/{$notifiable->id}",
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
            'event' => 'Có báo cáo công việc mới',
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

    private function toSms(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }
        $text = "Cong viec '{$item->name}' vua duoc nop bao cao, can xem. Tran trong !";

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
        $html = view('notifications.report_submitted.email', [
            'recipient' => $recipient,
            'item' => $item,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Báo cáo công việc mới: {$item->name}",
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $text = "Có báo cáo mới cho công việc: {$item->name}.";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'event' => 'Có báo cáo công việc mới',
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
            content: "'{$item->name}' vừa được nộp báo cáo, chờ bạn xem.",
            subject: 'Có báo cáo công việc mới',
            context: [
                'url' => "/task-assignment-items/{$item->id}",
                'type' => 'report_submitted',
            ],
        );
    }

    private function toTelegram(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->telegram_chat_id) {
            return null;
        }
        $text = "<b>Có báo cáo công việc mới</b>\n\n{$item->name} — chờ bạn xem.";

        return new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $recipient->telegram_chat_id, name: $recipient->name),
            content: $text,
        );
    }
}
