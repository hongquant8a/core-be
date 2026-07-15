<?php

namespace App\Services\Notification\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

interface Remindable
{
    /**
     * Mốc thời gian để tính remind_at (end_at / start_time / date_time+session)
     */
    public function getReminderDeadline(): ?Carbon;

    /**
     * Organization context
     */
    public function getReminderOrganizationId(): int;

    /**
     * module_key dùng để query NotificationEventConfig
     */
    public function getReminderModuleKey(): string;

    /**
     * event_keys dùng để query NotificationEventConfig (1 hoặc nhiều)
     */
    public function getReminderEventKeys(): array;

    /**
     * event_key truyền vào NotificationDispatcher khi fire
     */
    public function getReminderEventKey(?string $moment): string;

    /**
     * User nhận reminder
     */
    public function resolveReminderRecipients(): Collection;

    /**
     * Guest nhận reminder (chỉ Meeting có guest)
     */
    public function resolveGuestReminderRecipients(): Collection;

    /**
     * Kiểm tra model còn hợp lệ để fire reminder không
     */
    public function isValidForReminder(): bool;

    /**
     * Morph relation
     */
    public function reminders(): MorphMany;
}
