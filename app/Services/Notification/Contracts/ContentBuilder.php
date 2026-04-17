<?php

namespace App\Services\Notification\Contracts;

use App\Modules\Core\Models\User;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Database\Eloquent\Model;

interface ContentBuilder
{
    /**
     * Build a payload for a specific channel, or null to skip (e.g. recipient missing field).
     */
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload;

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string;

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string;

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array;
}
