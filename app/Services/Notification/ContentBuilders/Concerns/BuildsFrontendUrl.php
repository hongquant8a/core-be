<?php

namespace App\Services\Notification\ContentBuilders\Concerns;

use App\Modules\Meeting\Models\Meeting;

/**
 * Helper build deep-link URL FE cho notification của module Meeting.
 *
 * Path FE singular `/meeting/{id}` (không phải `/meetings/`).
 * Domain lấy từ APP_FRONTEND_URL (.env), fallback APP_URL.
 */
trait BuildsFrontendUrl
{
    protected function meetingFrontendUrl(Meeting $meeting): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base."/meeting/{$meeting->id}";
    }
}
