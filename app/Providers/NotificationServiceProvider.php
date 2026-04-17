<?php

namespace App\Providers;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\FcmChannel;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\ContentBuilders\DocumentIssuedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskCompletedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskConfirmedContentBuilder;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Events\TaskConfirmed;
use App\Services\Notification\Listeners\SendDocumentIssuedNotifications;
use App\Services\Notification\Listeners\SendTaskCompletedNotifications;
use App\Services\Notification\Listeners\SendTaskConfirmedNotifications;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentBuilderRegistry::class);

        $this->app->singleton(NotificationService::class, function ($app) {
            $settings = $app->make(SettingService::class);
            $smsClient = $app->make(SmsClient::class);

            return new NotificationService(
                channels: [
                    'sms' => new SmsChannel($smsClient, $settings),
                    'mail' => new MailChannel($settings),
                    'zalo' => new ZaloChannel($settings),
                    'fcm' => new FcmChannel($settings),
                ],
                logger: Log::channel('notification'),
            );
        });
    }

    public function boot(): void
    {
        // Register content builders
        $registry = $this->app->make(ContentBuilderRegistry::class);
        $registry->register('document_issued', $this->app->make(DocumentIssuedContentBuilder::class));
        $registry->register('task_completed', $this->app->make(TaskCompletedContentBuilder::class));
        $registry->register('task_confirmed', $this->app->make(TaskConfirmedContentBuilder::class));

        // Register event listeners
        Event::listen(DocumentIssued::class, SendDocumentIssuedNotifications::class);
        Event::listen(TaskCompleted::class, SendTaskCompletedNotifications::class);
        Event::listen(TaskConfirmed::class, SendTaskConfirmedNotifications::class);
    }
}
