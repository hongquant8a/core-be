<?php

namespace App\Providers;

use App\Modules\Core\Models\User;
use App\Modules\Core\Observers\UserObserver;
use App\Modules\Core\Services\SettingService;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingDiscussionRegistrationAttachment;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingPersonalNote;
use App\Modules\Meeting\Models\MeetingPersonalNoteAttachment;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\Meeting\Policies\MeetingAttendancePolicy;
use App\Modules\Meeting\Policies\MeetingDiscussionRegistrationAttachmentPolicy;
use App\Modules\Meeting\Policies\MeetingDiscussionRegistrationPolicy;
use App\Modules\Meeting\Policies\MeetingParticipantPolicy;
use App\Modules\Meeting\Policies\MeetingPersonalNoteAttachmentPolicy;
use App\Modules\Meeting\Policies\MeetingPersonalNotePolicy;
use App\Modules\Meeting\Policies\MeetingPolicy;
use App\Modules\Meeting\Policies\MeetingVoteResponsePolicy;
use App\Modules\Meeting\Policies\MeetingVoteTopicPolicy;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Observers\ScheduleObserver;
use App\Modules\Scheduling\Policies\SchedulePolicy;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Policies\SchedulingEmployeePolicy;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use App\Modules\Scheduling\Policies\SchedulingEmployeeGroupPolicy;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Policies\TaskAssignmentItemPolicy;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Modules\TaskAssignment\Policies\TaskAssignmentPetitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Throwable;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Morph map bắt buộc — MỌI model tham gia quan hệ polymorphic phải khai báo ở đây.
        // Áp cho: media.model_type (Spatie HasMedia), reminders.remindable_type,
        // notifications.notifiable_type, model_has_roles.model_type (Spatie permission).
        // Thiếu 1 alias → getMorphClass() ném ClassMorphViolationException → 500 khi ghi/đọc quan hệ đó.
        // Quy ước alias: snake_case số ít của tên Model.
        Relation::enforceMorphMap([
            // Core
            'user'                          => \App\Modules\Core\Models\User::class,
            'setting'                       => \App\Modules\Core\Models\Setting::class,
            'reminder'                      => \App\Models\Reminder::class,

            // Meeting
            'meeting'                       => \App\Modules\Meeting\Models\Meeting::class,
            'meeting_setting'               => \App\Modules\Meeting\Models\MeetingSetting::class,
            'meeting_document'              => \App\Modules\Meeting\Models\MeetingDocument::class,
            'meeting_discussion_registration' => \App\Modules\Meeting\Models\MeetingDiscussionRegistration::class,
            'meeting_invitation_template'   => \App\Modules\Meeting\Models\MeetingInvitationTemplate::class,
            'meeting_minutes_template'      => \App\Modules\Meeting\Models\MeetingMinutesTemplate::class,

            // Scheduling
            'schedule'                      => \App\Modules\Scheduling\Models\Schedule::class,

            // TaskAssignment
            'task_assignment_item'          => \App\Modules\TaskAssignment\Models\TaskAssignmentItem::class,
            'task_assignment_item_report'   => \App\Modules\TaskAssignment\Models\TaskAssignmentItemReport::class,
            'task_assignment_petition'      => \App\Modules\TaskAssignment\Models\TaskAssignmentPetition::class,
            'task_assignment_document'      => \App\Modules\TaskAssignment\Models\TaskAssignmentDocument::class,

            // Beneficiary — chỉ hai model có tệp đính kèm. Hồ sơ và thân nhân không gắn media.
            'beneficiary_type_relation'     => \App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::class,
            'beneficiary_document'          => \App\Modules\Beneficiary\Models\BeneficiaryDocument::class,
        ]);

        // Auto-create UserProfile mỗi khi tạo User.
        User::observe(UserObserver::class);

        // Track Schedule changes for notifications
        Schedule::observe(ScheduleObserver::class);

        // Register policies cho in-meeting control + public/participant view actions.
        // Spatie permission vẫn giữ cho admin catalog/CRUD setup; Policy gate cho mọi action gắn meeting cụ thể.
        // KHÔNG có Gate::before Super Admin bypass — admin hệ thống phải có role thật (chair/operator/participant)
        // trên meeting mới làm được action gắn meeting. Đồng bộ FE matrix.
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(MeetingVoteTopic::class, MeetingVoteTopicPolicy::class);
        Gate::policy(MeetingDiscussionRegistration::class, MeetingDiscussionRegistrationPolicy::class);
        Gate::policy(MeetingDiscussionRegistrationAttachment::class, MeetingDiscussionRegistrationAttachmentPolicy::class);
        Gate::policy(MeetingVoteResponse::class, MeetingVoteResponsePolicy::class);
        Gate::policy(MeetingAttendance::class, MeetingAttendancePolicy::class);
        Gate::policy(MeetingParticipant::class, MeetingParticipantPolicy::class);
        Gate::policy(MeetingPersonalNote::class, MeetingPersonalNotePolicy::class);
        Gate::policy(MeetingPersonalNoteAttachment::class, MeetingPersonalNoteAttachmentPolicy::class);

        // Register Scheduling Policies
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(SchedulingEmployee::class, SchedulingEmployeePolicy::class);
        Gate::policy(SchedulingEmployeeGroup::class, SchedulingEmployeeGroupPolicy::class);

        // Register TaskAssignment Policies
        Gate::policy(TaskAssignmentItem::class, TaskAssignmentItemPolicy::class);
        Gate::policy(TaskAssignmentPetition::class, TaskAssignmentPetitionPolicy::class);

        $this->loadViewsFrom(resource_path('views/scheduling'), 'scheduling');

        // Giữ nguyên header Excel khi import (không lowercase/snake_case).
        // Cho phép import dùng header tiếng Việt giống hệt template export.
        // Mỗi Import class tự dịch label → field key trong prepareForValidation.
        HeadingRowFormatter::default('none');

        Scribe::afterGenerating(function (array $paths) {
            if (! empty($paths['postman']) && file_exists($paths['postman'])) {
                $json = json_decode(file_get_contents($paths['postman']), true);
                file_put_contents(
                    $paths['postman'],
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                );
            }
        });

        View::composer('emails.notification-layout', function ($view) {
            $logoPath = null;
            $appName = null;
            $copyright = null;

            try {
                $settings = app(SettingService::class);
                $logoPath = $settings->getByKey('logo')['value'] ?? null;
                $appName = $settings->getByKey('organization_name')['value'] ?? null;
                $copyright = $settings->getByKey('copyright')['value'] ?? null;
            } catch (Throwable) {
                // Setting service unavailable — render with fallbacks.
            }

            $appUrl = rtrim((string) config('app.url', ''), '/');
            $logoUrl = $logoPath ? $appUrl . '/api' . $logoPath : null;

            $view->with([
                'logoUrl' => $logoUrl,
                'appName' => $appName ?: config('app.name', 'Hệ thống'),
                'copyright' => $copyright ?: null,
            ]);
        });
    }
}
