<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Jobs\SendGuestAccountRequestEmail;
use App\Modules\Core\Services\SettingService;

class GuestAccountRequestService
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Đọc cấu hình email liên hệ + email_enabled, dispatch job gửi mail.
     *
     * @return array{ok: bool, reason?: string}
     */
    public function handle(array $data): array
    {
        $contactEmail = $this->settingService->getByKey('contact_email')['value'] ?? null;
        $emailEnabled = (bool) ($this->settingService->getByKey('email_enabled')['value'] ?? false);

        if (! $contactEmail || ! $emailEnabled) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        SendGuestAccountRequestEmail::dispatch($contactEmail, $data);

        return ['ok' => true];
    }
}
