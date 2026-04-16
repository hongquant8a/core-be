<?php

namespace App\Providers;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            $settings = $app->make(SettingService::class);
            $smsClient = $app->make(SmsClient::class);

            return new NotificationService(
                channels: [
                    'sms' => new SmsChannel($smsClient, $settings),
                    'mail' => new MailChannel,
                    'zalo' => new ZaloChannel($settings),
                ],
                logger: Log::channel('notification'),
            );
        });
    }
}
