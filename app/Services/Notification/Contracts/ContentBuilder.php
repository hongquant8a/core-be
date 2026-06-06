<?php

namespace App\Services\Notification\Contracts;

use App\Modules\Core\Models\User;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Database\Eloquent\Model;

interface ContentBuilder
{
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload;

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string;

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string;

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array;

    /**
     * Dữ liệu flat key-value cho ZNS template.
     * Lấy từ cùng data truyền vào email blade.
     */
    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array;

    /**
     * Map { key => "Mô tả tiếng Việt" } cho FE cấu hình variable_mapping.
     */
    public function znsVariables(): array;
}
