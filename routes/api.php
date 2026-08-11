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

/*
|--------------------------------------------------------------------------
| Public API — không yêu cầu xác thực
|--------------------------------------------------------------------------
| Toàn bộ endpoint phục vụ trang public (citizen) gom vào prefix /api/public/.
| Quy ước URL:
|   - GET /api/public/{resource}            : danh sách công khai
|   - GET /api/public/{resource}/options    : dropdown (id/name/description)
|   - GET /api/public/{resource}/{id}       : chi tiết
|   - GET /api/public/meetings/{meeting}/{sub}: dữ liệu con của meeting (agendas, documents)
| Phân quyền (nếu có) qua Gate Policy (vd MeetingPolicy::viewPublic), KHÔNG Spatie.
*/
Route::prefix('public')->middleware('log.activity')->group(function () {
    // Hệ thống & tổ chức
    Route::get('/settings', [\App\Modules\Core\SettingController::class, 'public']);
    Route::get('/organizations', [\App\Modules\Core\OrganizationController::class, 'public']);
    Route::get('/organizations/options', [\App\Modules\Core\OrganizationController::class, 'publicOptions']);

    // TaskAssignment catalogs
    Route::get('/task-assignment-types', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'public']);
    Route::get('/task-assignment-types/options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'publicOptions']);
    Route::get('/task-assignment-item-types', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'public']);
    Route::get('/task-assignment-item-types/options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'publicOptions']);
    Route::get('/task-assignment-departments', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'public']);
    Route::get('/task-assignment-departments/options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'publicOptions']);

    // Export downloads qua signed URL (không auth:sanctum — xác thực bằng chữ ký,
    // đã được cấp quyền từ trước tại bước gọi .../export-link). Dùng cho Zalo Mini App
    // vì zmp-sdk downloadFile()/openWebview() chỉ nhận URL, không đính kèm được header.
    // Middleware 'signed' tự verify signature + expires, request hết hạn/sai chữ ký sẽ
    // bị 403 (xem Illuminate\Routing\Middleware\ValidateSignature).
    Route::get('/exports/task-assignment-items', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController::class, 'export'])
        ->middleware('signed')
        ->name('task-assignment-items.export.signed');

    // Meeting catalogs
    Route::get('/meeting-types', [\App\Modules\Meeting\Controllers\MeetingTypeController::class, 'public']);
    Route::get('/meeting-types/options', [\App\Modules\Meeting\Controllers\MeetingTypeController::class, 'publicOptions']);
    Route::get('/meeting-locations', [\App\Modules\Meeting\Controllers\MeetingLocationController::class, 'public']);
    Route::get('/meeting-locations/options', [\App\Modules\Meeting\Controllers\MeetingLocationController::class, 'publicOptions']);
    Route::get('/meeting-document-types', [\App\Modules\Meeting\Controllers\MeetingDocumentTypeController::class, 'public']);
    Route::get('/meeting-document-types/options', [\App\Modules\Meeting\Controllers\MeetingDocumentTypeController::class, 'publicOptions']);

    // Meetings — list + stats + show
    Route::get('/meetings', [\App\Modules\Meeting\Controllers\MeetingController::class, 'public']);
    Route::get('/meetings/document-tree', [\App\Modules\Meeting\Controllers\MeetingController::class, 'publicDocumentTree']);
    Route::get('/meetings/stats', [\App\Modules\Meeting\Controllers\MeetingController::class, 'publicStats']);
    Route::get('/meetings/{meeting}', [\App\Modules\Meeting\Controllers\MeetingController::class, 'publicShow'])->middleware('count.meeting.view');

    // Meeting sub-resources cho guest (Tab 1 Chương trình, Tab 2 Tài liệu).
    // Gate: MeetingPolicy::viewPublic (meeting is_public=true + status=published).
    Route::get('/meetings/{meeting}/agendas', [\App\Modules\Meeting\Controllers\MeetingAgendaController::class, 'publicListInMeeting']);
    Route::get('/meetings/{meeting}/documents', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'publicListInMeeting']);
    // Export tài liệu — auth-optional. Guest chỉ thấy doc is_public=true; chair/op/participant
    // thấy đầy đủ. Controller tự resolve auth qua Bearer/cookie + shouldSeeAllDocs.
    Route::get('/meetings/{meeting}/documents/export', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'exportInMeeting']);
    Route::get('/meetings/{meeting}/documents/export-views', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'exportViewsInMeeting']);

    // Meeting documents — list công khai (theo query meeting_id) + show + download (backward compat).
    Route::get('/meeting-documents', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'public']);
    Route::get('/meeting-documents/{meetingDocument}', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'publicShow'])->middleware('count.meeting.view');
    Route::get('/meeting-documents/{meetingDocument}/download', [\App\Modules\Meeting\Controllers\MeetingDocumentController::class, 'publicDownload']);

    // Scheduling — dropdown options cho grid lịch công tác (chủ trì, thành phần, lái xe)
    Route::get('/scheduling-employees/options', [\App\Modules\Scheduling\Controllers\SchedulingEmployeeController::class, 'options']);

});

// Route yêu cầu đăng nhập (Bearer token) và đặt ngữ cảnh team cho Spatie Permission
Route::middleware(['auth:sanctum', 'set.permissions.team', 'sync.fcm.token', 'log.activity'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // Zalo OA followers — sync 45p, auth-only, không Spatie. FE admin pick user_id để gán vào users.zalo_user_id.
    Route::get('/zalo-oa-followers', [\App\Modules\Core\ZaloOaFollowerController::class, 'index']);

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
    Route::prefix('task-assignment-employees')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_employee.php');
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

    // Đơn thư (phòng ban)
    Route::prefix('task-assignment-petitions')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_petition.php');
    });

    // Notification config scoped cho module Meeting
    Route::prefix('meeting/notification-config')->group(function () {
        require base_path('app/Modules/Meeting/Routes/notification_config.php');
    });

    // Notification config scoped cho module Scheduling (Lịch công tác)
    Route::prefix('schedules/notification-config')->group(function () {
        require base_path('app/Modules/Scheduling/Routes/notification_config.php');
    });

    // Notification Templates — ZNS template per module (query param: ?module=meeting|scheduling|task_assignment)
    Route::prefix('notification-templates')->group(function () {
        Route::get('/variables', [\App\Modules\Core\NotificationTemplateController::class, 'variables'])
            ->middleware('permission:notifications.templates.variables,web');
        Route::get('/', [\App\Modules\Core\NotificationTemplateController::class, 'index'])
            ->middleware('permission:notifications.templates.index,web');
        Route::post('/', [\App\Modules\Core\NotificationTemplateController::class, 'store'])
            ->middleware('permission:notifications.templates.store,web');
        Route::put('/{template}', [\App\Modules\Core\NotificationTemplateController::class, 'update'])
            ->middleware('permission:notifications.templates.update,web');
        Route::delete('/{template}', [\App\Modules\Core\NotificationTemplateController::class, 'destroy'])
            ->middleware('permission:notifications.templates.destroy,web');
    });

    // Meeting module — route phẳng cho admin catalog/CRUD setup (Spatie permission).
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
    Route::prefix('meeting-vote-topics')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_vote_topic.php');
    });
    // Template biên bản (.docx) — mỗi tổ chức có template riêng (logo, layout).
    Route::prefix('meeting-minutes-templates')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_minutes_template.php');
    });
    // Template giấy mời (.docx) — mỗi tổ chức có template riêng.
    Route::prefix('meeting-invitation-templates')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_invitation_template.php');
    });
    // Cấu hình cuộc họp — singleton per org (auto find-or-create theo X-Organization-Id).
    Route::prefix('meeting-settings')->group(function () {
        require base_path('app/Modules/Meeting/Routes/meeting_setting.php');
    });

    // Chat nội bộ — engine dùng chung cho DM (toàn hệ thống) và chat nhóm theo cuộc họp
    // (khi meeting.internal_chat_enabled = true).
    Route::prefix('chat')->group(function () {
        require base_path('app/Modules/Core/Routes/chat.php');
    });
    // Admin: xem/xoá lịch sử chat nhóm theo cuộc họp (permission riêng, destroy chỉ Super Admin).
    Route::prefix('meeting-chat-conversations')->group(function () {
        require base_path('app/Modules/Core/Routes/meeting_chat_conversation.php');
    });

    // Scheduling module
    Route::prefix('schedules')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Scheduling/Routes/schedule.php');
    });
    Route::prefix('scheduling-employees')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Scheduling/Routes/scheduling_employee.php');
    });
    Route::prefix('scheduling-employee-groups')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Scheduling/Routes/scheduling_employee_group.php');
    });
    Route::prefix('scheduling-settings')->group(function () {
        require base_path('app/Modules/Scheduling/Routes/scheduling_setting.php');
    });
});
