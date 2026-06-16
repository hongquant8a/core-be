<?php

namespace App\Providers;

use App\Modules\Core\Services\SettingService;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Observers\MeetingObserver;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Observers\TaskAssignmentItemObserver;
use App\Services\Notification\Channels\FcmChannel;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Channels\TelegramChannel;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\Channels\ZaloZnsChannel;
use App\Services\Notification\ContentBuilders\DocumentIssuedContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingCancelledContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingPublishedContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingReminderContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingUpdatedContentBuilder;
use App\Services\Notification\ContentBuilders\ReminderContentBuilder;
use App\Services\Notification\ContentBuilders\TaskAssignedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskCompletedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskConfirmedContentBuilder;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Events\MeetingCancelled;
use App\Services\Notification\Events\MeetingPublished;
use App\Services\Notification\Events\MeetingUpdated;
use App\Services\Notification\Events\TaskAssigned;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Events\TaskConfirmed;
use App\Services\Notification\Listeners\SendDocumentIssuedNotifications;
use App\Services\Notification\Listeners\SendMeetingCancelledNotifications;
use App\Services\Notification\Listeners\SendMeetingPublishedNotifications;
use App\Services\Notification\Listeners\SendMeetingUpdatedNotifications;
use App\Services\Notification\Listeners\SendTaskAssignedNotifications;
use App\Services\Notification\Listeners\SendTaskCompletedNotifications;
use App\Services\Notification\Listeners\SendTaskConfirmedNotifications;
use App\Services\Notification\ContentBuilders\SchedulePublishedContentBuilder;
use App\Services\Notification\ContentBuilders\ScheduleUpdatedContentBuilder;
use App\Services\Notification\ContentBuilders\ScheduleCancelledContentBuilder;
use App\Services\Notification\ContentBuilders\ScheduleReminderContentBuilder;
use App\Services\Notification\Events\SchedulePublished;
use App\Services\Notification\Events\ScheduleUpdated;
use App\Services\Notification\Events\ScheduleCancelled;
use App\Services\Notification\Listeners\SendSchedulePublishedNotifications;
use App\Services\Notification\Listeners\SendScheduleUpdatedNotifications;
use App\Services\Notification\Listeners\SendScheduleCancelledNotifications;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationTemplateService;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentBuilderRegistry::class);

        $this->app->singleton(NotificationTemplateService::class);

        $this->app->singleton(NotificationService::class, function ($app) {
            $settings = $app->make(SettingService::class);
            $smsClient = $app->make(SmsClient::class);

            return new NotificationService(
                channels: [
                    'sms' => new SmsChannel($smsClient, $settings),
                    'mail' => new MailChannel($settings),
                    // Zalo OA Message (free-form text qua user_id, key: 'zalo') — ZaloChannel.php
                    'zalo'     => new ZaloChannel($settings),
                    // Zalo ZNS template qua WorldSMS relay (key: 'zalo_zns') — ZaloZnsChannel.php
                    'zalo_zns' => new ZaloZnsChannel($settings),
                    'fcm'      => new FcmChannel($settings),
                    'telegram' => new TelegramChannel($settings),
                ],
            );
        });
    }

    public function boot(): void
    {
        // Register content builders
        $registry = $this->app->make(ContentBuilderRegistry::class);
        $registry->register('document_issued', $this->app->make(DocumentIssuedContentBuilder::class));
        $registry->register('task_assigned', $this->app->make(TaskAssignedContentBuilder::class));
        $registry->register('task_completed', $this->app->make(TaskCompletedContentBuilder::class));
        $registry->register('task_confirmed', $this->app->make(TaskConfirmedContentBuilder::class));
        $registry->register('reminder_before', new ReminderContentBuilder('before'));
        $registry->register('reminder_on', new ReminderContentBuilder('on'));
        $registry->register('reminder_after', new ReminderContentBuilder('after'));
        $registry->register('meeting_published', $this->app->make(MeetingPublishedContentBuilder::class));
        $registry->register('meeting_updated', $this->app->make(MeetingUpdatedContentBuilder::class));
        $registry->register('meeting_cancelled', $this->app->make(MeetingCancelledContentBuilder::class));
        $registry->register('meeting_reminder_before', new MeetingReminderContentBuilder('before'));
        $registry->register('meeting_reminder_on', new MeetingReminderContentBuilder('on'));
        $registry->register('meeting_reminder_after', new MeetingReminderContentBuilder('after'));

        // Register Scheduling Content Builders
        $registry->register('schedule_published', $this->app->make(SchedulePublishedContentBuilder::class));
        $registry->register('schedule_updated', $this->app->make(ScheduleUpdatedContentBuilder::class));
        $registry->register('schedule_cancelled', $this->app->make(ScheduleCancelledContentBuilder::class));
        $registry->register('schedule_reminder', $this->app->make(ScheduleReminderContentBuilder::class));

        // Register event listeners
        Event::listen(DocumentIssued::class, SendDocumentIssuedNotifications::class);
        Event::listen(TaskAssigned::class, SendTaskAssignedNotifications::class);
        Event::listen(TaskCompleted::class, SendTaskCompletedNotifications::class);
        Event::listen(TaskConfirmed::class, SendTaskConfirmedNotifications::class);
        Event::listen(MeetingPublished::class, SendMeetingPublishedNotifications::class);
        Event::listen(MeetingUpdated::class, SendMeetingUpdatedNotifications::class);
        Event::listen(MeetingCancelled::class, SendMeetingCancelledNotifications::class);
        Event::listen(SchedulePublished::class, SendSchedulePublishedNotifications::class);
        Event::listen(ScheduleUpdated::class, SendScheduleUpdatedNotifications::class);
        Event::listen(ScheduleCancelled::class, SendScheduleCancelledNotifications::class);

        // Register model observer for auto reminder scheduling
        TaskAssignmentItem::observe(TaskAssignmentItemObserver::class);
        Meeting::observe(MeetingObserver::class);
    }
}
