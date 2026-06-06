<?php

namespace App\Services\Notification\ContentBuilders\Concerns;

use App\Modules\Core\Models\User;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Database\Eloquent\Model;

trait BuildZns
{
    protected function buildZnsPayload(User $recipient, Model $notifiable): ?NotificationPayload
    {
        if (! $recipient->phone) {
            return null;
        }

        return new NotificationPayload(
            channels: ['zalo_zns'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: $this->shortBody($recipient, $notifiable),
            context: $this->znsContext($recipient, $notifiable),
        );
    }
}
