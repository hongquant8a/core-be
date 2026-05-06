<?php

use App\Http\Controllers\DeployController;
use App\Modules\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Deploy webhook - public, xác thực bằng HMAC sha256 trong controller
Route::post('/deploy/webhook', [DeployController::class, 'handle']);

// Auth module - public routes (đăng nhập, quên mật khẩu, đặt lại mật khẩu)
Route::prefix('auth')->middleware('log.activity')->group(function () {
    require base_path('app/Modules/Auth/Routes/auth.php');
});

// Cấu hình công khai - không cần xác thực
Route::get('/settings/public', [\App\Modules\Core\SettingController::class, 'public'])->middleware('log.activity');
Route::get('/organizations/public', [\App\Modules\Core\OrganizationController::class, 'public'])->middleware('log.activity');
Route::get('/organizations/public-options', [\App\Modules\Core\OrganizationController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-types/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-types/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-item-types/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-item-types/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-departments/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-departments/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/meeting-types/public', [\App\Modules\Meeting\MeetingTypeController::class, 'public'])->middleware('log.activity');
Route::get('/meeting-types/public-options', [\App\Modules\Meeting\MeetingTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/meeting-locations/public', [\App\Modules\Meeting\MeetingLocationController::class, 'public'])->middleware('log.activity');
Route::get('/meeting-locations/public-options', [\App\Modules\Meeting\MeetingLocationController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/meeting-document-types/public', [\App\Modules\Meeting\MeetingDocumentTypeController::class, 'public'])->middleware('log.activity');
Route::get('/meeting-document-types/public-options', [\App\Modules\Meeting\MeetingDocumentTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/meetings/public', [\App\Modules\Meeting\MeetingController::class, 'public'])->middleware('log.activity');
Route::get('/meetings/public/stats', [\App\Modules\Meeting\MeetingController::class, 'publicStats'])->middleware('log.activity');
Route::get('/meetings/public/{meeting}', [\App\Modules\Meeting\MeetingController::class, 'publicShow'])->middleware(['log.activity', 'count.meeting.view']);
Route::get('/meeting-documents/public', [\App\Modules\Meeting\MeetingDocumentController::class, 'public'])->middleware('log.activity');
Route::get('/meeting-documents/public/{meetingDocument}', [\App\Modules\Meeting\MeetingDocumentController::class, 'publicShow'])->middleware('log.activity');
Route::get('/meeting-documents/public/{meetingDocument}/download', [\App\Modules\Meeting\MeetingDocumentController::class, 'publicDownload'])->middleware('log.activity');

// Route yêu cầu đăng nhập (Bearer token) và đặt ngữ cảnh team cho Spatie Permission
Route::middleware(['auth:sanctum', 'set.permissions.team', 'sync.fcm.token', 'log.activity'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::prefix('users')->group(function () {
        require base_path('app/Modules/Core/Routes/user.php');
    });
    Route::prefix('permissions')->group(function () {
        require base_path('app/Modules/Core/Routes/permission.php');
    });
    Route::prefix('roles')->group(function () {
        require base_path('app/Modules/Core/Routes/role.php');
    });
    Route::prefix('organizations')->group(function () {
        require base_path('app/Modules/Core/Routes/organization.php');
    });
    Route::prefix('log-activities')->group(function () {
        require base_path('app/Modules/Core/Routes/log_activity.php');
    });
    Route::prefix('settings')->group(function () {
        require base_path('app/Modules/Core/Routes/setting.php');
    });
    Route::prefix('notifications')->group(function () {
        require base_path('app/Modules/Core/Routes/notification.php');
    });

    // TaskAssignment module - không scope organization_id
    Route::prefix('task-assignment-departments')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_department.php');
    });
    Route::prefix('task-assignment-types')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_type.php');
    });
    Route::prefix('task-assignment-item-types')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item_type.php');
    });
    Route::prefix('task-assignment-documents')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_document.php');
    });
    Route::prefix('task-assignment-items')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item.php');
    });
    Route::prefix('task-assignment-item-reports')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item_report.php');
    });

    // Timeline routes (nested under item)
    Route::prefix('task-assignment-items/{taskAssignmentItem}/transfers')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_transfer.php');
    });
    Route::prefix('task-assignment-items/{taskAssignmentItem}/notes')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_note.php');
    });

    // Notification config scoped cho module TaskAssignment
    Route::prefix('task-assignment/notification-config')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/notification_config.php');
    });

    // Notification config scoped cho module Meeting
    Route::prefix('meeting/notification-config')->group(function () {
        require base_path('app/Modules/Meeting/Routes/notification_config.php');
    });

    // Meeting module
        Route::prefix('meetings')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting.php');
    });
    Route::prefix('meeting-types')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_type.php');
    });
    Route::prefix('meeting-locations')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_location.php');
    });
    Route::prefix('meeting-document-types')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_document_type.php');
    });
    Route::prefix('meeting-attendee-groups')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_attendee_group.php');
    });
    Route::prefix('meeting-attendees')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_attendee.php');
    });
    Route::prefix('meeting-agendas')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_agenda.php');
    });
    Route::prefix('meeting-documents')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_document.php');
    });
    Route::prefix('meeting-participants')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_participant.php');
    });
    Route::prefix('meeting-attendances')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_attendance.php');
    });
    Route::prefix('meeting-vote-topics')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_vote_topic.php');
    });
    Route::prefix('meeting-vote-responses')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_vote_response.php');
    });
    Route::prefix('meeting-conclusions')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_conclusion.php');
    });
    Route::prefix('meeting-discussion-registrations')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_discussion_registration.php');
    });
    Route::prefix('meeting-personal-notes')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_personal_note.php');
    });
    Route::prefix('meeting-personal-note-attachments')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_personal_note_attachment.php');
    });
});
