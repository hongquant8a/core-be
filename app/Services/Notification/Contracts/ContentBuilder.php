<?php

namespace App\Services\Notification\Contracts;

use App\Modules\Core\Models\User;
use App\Services\Notification\DTOs\NotificationPayload;

interface ContentBuilder
{
    /**
     * Build a payload for a specific channel, or null to skip (e.g. recipient missing field).
     */
    public function build(string $channelKey, User $recipient, mixed ...$args): ?NotificationPayload;

    /**
     * In-app notification title (displayed in list).
     */
    public function title(User $recipient, mixed ...$args): string;

    /**
     * In-app short body (displayed in list).
     */
    public function shortBody(User $recipient, mixed ...$args): string;

    /**
     * Extra context for in-app (e.g. url, action).
     */
    public function inAppContext(User $recipient, mixed ...$args): array;
}
