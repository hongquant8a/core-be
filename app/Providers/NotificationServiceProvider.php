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
use App\Services\Notification\ContentBuilders\DeadlineExtensionRequestedContentBuilder;
use App\Services\Notification\ContentBuilders\DeadlineExtensionReviewedContentBuilder;
use App\Services\Notification\ContentBuilders\DocumentIssuedContentBuilder;
use App\Services\Notification\ContentBuilders\DocumentUpdatedContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingCancelledContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingPublishedContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingReminderContentBuilder;
use App\Services\Notification\ContentBuilders\MeetingUpdatedContentBuilder;
use App\Services\Notification\ContentBuilders\NoteAddedContentBuilder;
use App\Services\Notification\ContentBuilders\PetitionCreatedContentBuilder;
use App\Services\Notification\ContentBuilders\PetitionStatusChangedContentBuilder;
use App\Services\Notification\ContentBuilders\ReminderContentBuilder;
use App\Services\Notification\ContentBuilders\ReportSubmittedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskAssignedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskCompletedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskConfirmedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskDeadlineChangedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskRejectedContentBuilder;
use App\Services\Notification\ContentBuilders\TaskStatusChangedContentBuilder;
use App\Services\Notification\Events\DeadlineExtensionRequested;
use App\Services\Notification\Events\DeadlineExtensionReviewed;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Events\DocumentUpdated;
use App\Services\Notification\Events\MeetingCancelled;
use App\Services\Notification\Events\MeetingPublished;
use App\Services\Notification\Events\MeetingUpdated;
use App\Services\Notification\Events\NoteAdded;
use App\Services\Notification\Events\PetitionCreated;
use App\Services\Notification\Events\PetitionStatusChanged;
use App\Services\Notification\Events\ReportSubmitted;
use App\Services\Notification\Events\TaskAssigned;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Events\TaskConfirmed;
use App\Services\Notification\Events\TaskDeadlineChanged;
use App\Services\Notification\Events\TaskRejected;
use App\Services\Notification\Events\TaskStatusChanged;
use App\Services\Notification\Listeners\SendDeadlineExtensionRequestedNotifications;
use App\Services\Notification\Listeners\SendDeadlineExtensionReviewedNotifications;
use App\Services\Notification\Listeners\SendDocumentIssuedNotifications;
use App\Services\Notification\Listeners\SendDocumentUpdatedNotifications;
use App\Services\Notification\Listeners\SendMeetingCancelledNotifications;
use App\Services\Notification\Listeners\SendMeetingPublishedNotifications;
use App\Services\Notification\Listeners\SendMeetingUpdatedNotifications;
use App\Services\Notification\Listeners\SendNoteAddedNotifications;
use App\Services\Notification\Listeners\SendPetitionCreatedNotifications;
use App\Services\Notification\Listeners\SendPetitionStatusChangedNotifications;
use App\Services\Notification\Listeners\SendReportSubmittedNotifications;
use App\Services\Notification\Listeners\SendTaskAssignedNotifications;
use App\Services\Notification\Listeners\SendTaskCompletedNotifications;
use App\Services\Notification\Listeners\SendTaskConfirmedNotifications;
use App\Services\Notification\Listeners\SendTaskDeadlineChangedNotifications;
use App\Services\Notification\Listeners\SendTaskRejectedNotifications;
use App\Services\Notification\Listeners\SendTaskStatusChangedNotifications;
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
                    'zalo' => new ZaloChannel($settings),
                    // Zalo ZNS template qua WorldSMS relay (key: 'zalo_zns') — ZaloZnsChannel.php
                    'zalo_zns' => new ZaloZnsChannel($settings),
                    'fcm' => new FcmChannel($settings),
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
        $registry->register('report_submitted', $this->app->make(ReportSubmittedContentBuilder::class));
        $registry->register('task_rejected', $this->app->make(TaskRejectedContentBuilder::class));
        $registry->register('deadline_extension_requested', $this->app->make(DeadlineExtensionRequestedContentBuilder::class));
        $registry->register('deadline_extension_reviewed', $this->app->make(DeadlineExtensionReviewedContentBuilder::class));
        $registry->register('task_deadline_changed', $this->app->make(TaskDeadlineChangedContentBuilder::class));
        $registry->register('task_status_changed', $this->app->make(TaskStatusChangedContentBuilder::class));
        $registry->register('note_added', $this->app->make(NoteAddedContentBuilder::class));
        $registry->register('petition_created', $this->app->make(PetitionCreatedContentBuilder::class));
        $registry->register('petition_status_changed', $this->app->make(PetitionStatusChangedContentBuilder::class));
        $registry->register('document_updated', $this->app->make(DocumentUpdatedContentBuilder::class));
        $registry->register('reminder_before', new ReminderContentBuilder('before'));
        $registry->register('reminder_on', new ReminderContentBuilder('on'));
        $registry->register('reminder_after', new ReminderContentBuilder('after'));
        $registry->register('meeting_published', $this->app->make(MeetingPublishedContentBuilder::class));
        $registry->register('meeting_updated', $this->app->make(MeetingUpdatedContentBuilder::class));
        $registry->register('meeting_cancelled', $this->app->make(MeetingCancelledContentBuilder::class));
        $registry->register('meeting_reminder_before', new MeetingReminderContentBuilder('before'));
        $registry->register('meeting_reminder_on', new MeetingReminderContentBuilder('on'));
        $registry->register('meeting_reminder_after', new MeetingReminderContentBuilder('after'));

        // Register event listeners
        Event::listen(DocumentIssued::class, SendDocumentIssuedNotifications::class);
        Event::listen(TaskAssigned::class, SendTaskAssignedNotifications::class);
        Event::listen(TaskCompleted::class, SendTaskCompletedNotifications::class);
        Event::listen(TaskConfirmed::class, SendTaskConfirmedNotifications::class);
        Event::listen(ReportSubmitted::class, SendReportSubmittedNotifications::class);
        Event::listen(TaskRejected::class, SendTaskRejectedNotifications::class);
        Event::listen(DeadlineExtensionRequested::class, SendDeadlineExtensionRequestedNotifications::class);
        Event::listen(DeadlineExtensionReviewed::class, SendDeadlineExtensionReviewedNotifications::class);
        Event::listen(TaskDeadlineChanged::class, SendTaskDeadlineChangedNotifications::class);
        Event::listen(TaskStatusChanged::class, SendTaskStatusChangedNotifications::class);
        Event::listen(NoteAdded::class, SendNoteAddedNotifications::class);
        Event::listen(PetitionCreated::class, SendPetitionCreatedNotifications::class);
        Event::listen(PetitionStatusChanged::class, SendPetitionStatusChangedNotifications::class);
        Event::listen(DocumentUpdated::class, SendDocumentUpdatedNotifications::class);
        Event::listen(MeetingPublished::class, SendMeetingPublishedNotifications::class);
        Event::listen(MeetingUpdated::class, SendMeetingUpdatedNotifications::class);
        Event::listen(MeetingCancelled::class, SendMeetingCancelledNotifications::class);

        // Register model observer for auto reminder scheduling
        TaskAssignmentItem::observe(TaskAssignmentItemObserver::class);
        Meeting::observe(MeetingObserver::class);
    }
}
