<?php

namespace App\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\ContentBuilders\Concerns\BuildZns;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskCompletedContentBuilder implements ContentBuilder
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
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Công việc đã báo cáo hoàn thành';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        if ($notifiable instanceof TaskAssignmentItem) {
            return "Công việc \"{$notifiable->name}\" đã được báo cáo hoàn thành, chờ bạn xác nhận.";
        }

        return 'Có công việc cần xác nhận.';
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
        if (! $notifiable instanceof TaskAssignmentItem) return [];
        return [
            'customer_name' => $recipient->name,
            'gender' => $recipient->gender ?? 'Anh/Chị',
            'task_name' => $notifiable->name,
            'deadline' => $notifiable->end_at?->format('H:i d/m/Y') ?? '',
            'code_id' => (string) $notifiable->id,
            'event' => 'Công việc báo cáo hoàn thành',
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
        $text = "Cong viec '{$item->name}' da bao cao hoan thanh, can xac nhan. Tran trong !";

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
        $html = view('notifications.task_completed.email', [
            'recipient' => $recipient,
            'item' => $item,
        ])->render();

        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $recipient->email, name: $recipient->name),
            content: $html,
            subject: "Công việc chờ xác nhận: {$item->name}",
        );
    }

    private function toZalo(User $recipient, TaskAssignmentItem $item): ?NotificationPayload
    {
        if (! $recipient->zalo_user_id) {
            return null;
        }

        $text = "Công việc đã hoàn thành: {$item->name}.";

        return new NotificationPayload(
            channels: ['zalo'],
            recipient: new Recipient(zaloId: $recipient->zalo_user_id, name: $recipient->name),
            content: $text,
            context: [
                'customer_name' => $recipient->name,
                'task_name' => $item->name,
                'event' => 'Công việc báo cáo hoàn thành',
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
            content: "'{$item->name}' đã báo cáo hoàn thành, chờ xác nhận.",
            subject: 'Công việc chờ xác nhận',
            context: [
                'url' => "/task-assignment-items/{$item->id}",
                'type' => 'task_completed',
            ],
        );
    }
}
