<?php

use App\Modules\Meeting\Controllers\MeetingAttendanceController;
use App\Modules\Meeting\Controllers\MeetingController;
use App\Modules\Meeting\Controllers\MeetingDiscussionRegistrationController;
use App\Modules\Meeting\Controllers\MeetingParticipantController;
use App\Modules\Meeting\Controllers\MeetingPersonalNoteAttachmentController;
use App\Modules\Meeting\Controllers\MeetingPersonalNoteController;
use App\Modules\Meeting\Controllers\MeetingVoteResponseController;
use App\Modules\Meeting\Controllers\MeetingVoteTopicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meeting routes — 2 layer phân quyền
|--------------------------------------------------------------------------
| 1. Catalog/CRUD admin (route phẳng): Spatie permission meetings.{action}.
|    Dùng cho admin setup khi tạo meeting + dashboard.
|
| 2. In-meeting / participant actions (route nested /meetings/{meeting}/...):
|    Gate Policy. Check qua FK của meeting (chairperson_meeting_attendee_id,
|    operator_meeting_attendee_id) + list participants → user.id.
|
| Quy ước ability:
|   - can:viewParticipant,meeting   — user có role (chair/op/participant)
|   - can:participate,meeting       — alias self-action (checkin, mark-absent, đăng ký)
|   - can:operate,meeting           — chair/op (export, manual checkin, reorder)
|   - can:manageAttendance,meeting  — chair/op (lock/unlock attendance)
|   - can:endEarly,meeting          — chair/op (end early)
|   - can:highlight,meeting         — chair/op (highlight agenda/discussion)
|
| KHÔNG Super Admin bypass — admin hệ thống phải có role thật trên meeting.
*/

// ───────────────────────────── 1. Catalog/CRUD admin ─────────────────────────────

// Export biên bản .docx từ template — auth-only, gate operate pure FK (chair/op của meeting).
Route::post('/{meeting}/export-minutes', [\App\Modules\Meeting\Controllers\MeetingMinutesTemplateController::class, 'exportMinutes']);
// List template biên bản cho dialog "Chọn template" trước export.
Route::get('/{meeting}/minutes-templates', [\App\Modules\Meeting\Controllers\MeetingMinutesTemplateController::class, 'indexInMeeting'])->middleware('can:exportReports,meeting');



Route::delete('/bulk-delete', [MeetingController::class, 'bulkDestroy'])->middleware('permission:meetings.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingController::class, 'bulkUpdateStatus'])->middleware('permission:meetings.bulkUpdateStatus,web');
Route::get('/export', [MeetingController::class, 'export'])->middleware('permission:meetings.export,web');
Route::get('/stats', [MeetingController::class, 'stats'])->middleware('permission:meetings.stats,web');
Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings.index,web');
Route::get('/{meeting}', [MeetingController::class, 'show'])->middleware(['permission:meetings.show,web', 'count.meeting.view']);
Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meetings.store,web');
Route::put('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::patch('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->middleware('permission:meetings.destroy,web');
Route::patch('/{meeting}/status', [MeetingController::class, 'changeStatus'])->middleware('permission:meetings.changeStatus,web');
Route::patch('/{meeting}/reopen', [MeetingController::class, 'reopen'])->middleware('permission:meetings.changeStatus,web');

// ───────────────────── 2. In-meeting control (chair/operator) ────────────────────

// Tab 7 Điều hành — thao tác nhanh.
Route::patch('/{meeting}/lock-attendance', [MeetingController::class, 'lockAttendance'])->middleware('can:manageAttendance,meeting');
Route::patch('/{meeting}/unlock-attendance', [MeetingController::class, 'unlockAttendance'])->middleware('can:manageAttendance,meeting');
Route::patch('/{meeting}/end-early', [MeetingController::class, 'endEarly'])->middleware('can:endEarly,meeting');
Route::patch('/{meeting}/highlight-agenda', [MeetingController::class, 'highlightAgenda'])->middleware('can:highlight,meeting');
Route::patch('/{meeting}/highlight-discussion', [MeetingController::class, 'highlightDiscussion'])->middleware('can:highlight,meeting');

// Tab 5 QR — Gate Policy (khóa ngoại): chair OR operator OR meeting.qr_manager_user_id.
Route::get('/{meeting}/qr-token', [MeetingController::class, 'qrToken'])->middleware('can:showQrCode,meeting');

// ─────────────────── 3. Nested sub-resources (gate policy) ───────────────────────

// Tab 3 Thảo luận & Chất vấn.
Route::prefix('{meeting}/discussion-registrations')->group(function () {
    Route::get('/stats', [MeetingDiscussionRegistrationController::class, 'statsInMeeting'])->middleware('can:viewParticipant,meeting');
    // Export full — chỉ chair/op (FK pure, không Spatie fallback admin).
    Route::get('/export', [MeetingDiscussionRegistrationController::class, 'exportInMeeting'])->middleware('can:operate,meeting');
    // Export-mine — đại biểu (bao gồm chair/op) tự xuất đăng ký của mình.
    Route::get('/export-mine', [MeetingDiscussionRegistrationController::class, 'exportMineInMeeting'])->middleware('can:participate,meeting');
    Route::get('/', [MeetingDiscussionRegistrationController::class, 'indexInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::post('/', [MeetingDiscussionRegistrationController::class, 'storeInMeeting'])->middleware('can:participate,meeting');
    Route::patch('/reorder', [MeetingDiscussionRegistrationController::class, 'reorderInMeeting'])->middleware('can:operate,meeting');
    Route::get('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'showInMeeting'])->middleware('can:view,meetingDiscussionRegistration');
    Route::put('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'updateInMeeting'])->middleware('can:update,meetingDiscussionRegistration');
    Route::patch('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'updateInMeeting'])->middleware('can:update,meetingDiscussionRegistration');
    Route::delete('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'destroyInMeeting'])->middleware('can:delete,meetingDiscussionRegistration');
    Route::patch('/{meetingDiscussionRegistration}/complete', [MeetingDiscussionRegistrationController::class, 'completeInMeeting'])->middleware('can:complete,meetingDiscussionRegistration');
});

// Attachments nested 2 cấp: meeting → registration → attachments. Multi-file đính kèm đăng ký.
// Gate update/delete trên Policy của attachment item (owner đăng ký HOẶC chair/op meeting).
// LƯU Ý: Symfony route variable max 32 ký tự. `meetingDiscussionRegistrationAttachment` (39) quá dài →
// dùng tên ngắn `discAttachment` + explicit Route::model binding để Route Model Binding vẫn resolve.
Route::model('discAttachment', \App\Modules\Meeting\Models\MeetingDiscussionRegistrationAttachment::class);
Route::prefix('{meeting}/discussion-registrations/{meetingDiscussionRegistration}/attachments')->group(function () {
    Route::post('/', [\App\Modules\Meeting\Controllers\MeetingDiscussionRegistrationAttachmentController::class, 'storeInRegistration'])
        ->middleware('can:update,meetingDiscussionRegistration');
    Route::patch('/reorder', [\App\Modules\Meeting\Controllers\MeetingDiscussionRegistrationAttachmentController::class, 'reorderInRegistration'])
        ->middleware('can:update,meetingDiscussionRegistration');
    Route::delete('/{discAttachment}', [\App\Modules\Meeting\Controllers\MeetingDiscussionRegistrationAttachmentController::class, 'destroyInRegistration'])
        ->middleware('can:delete,discAttachment');
});

// Tab 4 Biểu quyết — view topics (participant+), cast vote, open/close (chair/op).
Route::prefix('{meeting}/vote-topics')->group(function () {
    Route::get('/', [MeetingVoteTopicController::class, 'indexInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::get('/{meetingVoteTopic}', [MeetingVoteTopicController::class, 'showInMeeting'])->middleware('can:view,meetingVoteTopic');
    Route::patch('/{meetingVoteTopic}/open', [MeetingVoteTopicController::class, 'openInMeeting'])->middleware('can:open,meetingVoteTopic');
    Route::patch('/{meetingVoteTopic}/close', [MeetingVoteTopicController::class, 'closeInMeeting'])->middleware('can:close,meetingVoteTopic');
    // Cast vote — gate MeetingVoteTopicPolicy::cast (participant OR chair, NOT operator).
    Route::post('/{meetingVoteTopic}/responses', [MeetingVoteResponseController::class, 'castInTopic'])->middleware('can:cast,meetingVoteTopic');
});

// Tab 4 Biểu quyết — view responses (chair/op dashboard).
Route::prefix('{meeting}/vote-responses')->group(function () {
    // Stats aggregate — service tự gate theo topic.show_result_on_personal_device:
    // flag=true cho phép đại biểu xem tổng hợp (không lộ phiếu cá nhân);
    // flag=false service throw 403 với message rõ ràng. Route gate chỉ check là role nào đó.
    Route::get('/stats', [MeetingVoteResponseController::class, 'statsInMeeting'])->middleware('can:viewParticipant,meeting');
    // Detail (mỗi row 1 phiếu, có thể anonymize) — sensitive, giữ operate only.
    Route::get('/export', [MeetingVoteResponseController::class, 'exportInMeeting'])->middleware('can:operate,meeting');
    // Summary (đếm theo option) — chair/op pure FK, đồng bộ triết lý in-meeting.
    Route::get('/export-summary', [MeetingVoteResponseController::class, 'exportSummaryInMeeting'])->middleware('can:operate,meeting');
    Route::get('/', [MeetingVoteResponseController::class, 'indexInMeeting'])->middleware('can:operate,meeting');
});

// Tab 5 Điểm danh — self checkin/markAbsent (participant); chair/op manual/approve/reject.
Route::prefix('{meeting}/attendances')->group(function () {
    Route::get('/stats', [MeetingAttendanceController::class, 'statsInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::get('/export', [MeetingAttendanceController::class, 'exportInMeeting'])->middleware('can:operate,meeting');
    Route::get('/', [MeetingAttendanceController::class, 'indexInMeeting'])->middleware('can:operate,meeting');
    Route::post('/checkin', [MeetingAttendanceController::class, 'checkinInMeeting'])->middleware('can:participate,meeting');
    Route::post('/checkin-by-token', [MeetingAttendanceController::class, 'checkinByTokenInMeeting'])->middleware('can:participate,meeting');
    Route::post('/mark-absent', [MeetingAttendanceController::class, 'markAbsentInMeeting'])->middleware('can:participate,meeting');
    Route::post('/manual-checkin', [MeetingAttendanceController::class, 'manualCheckinInMeeting'])->middleware('can:manageAttendance,meeting');
    Route::patch('/{meetingAttendance}/approve', [MeetingAttendanceController::class, 'approveInMeeting'])->middleware('can:approve,meetingAttendance');
    Route::patch('/{meetingAttendance}/reject', [MeetingAttendanceController::class, 'rejectInMeeting'])->middleware('can:reject,meetingAttendance');
});

// Participants — list participant+ xem; self respond invitation.
Route::prefix('{meeting}/participants')->group(function () {
    Route::get('/stats', [MeetingParticipantController::class, 'statsInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::get('/', [MeetingParticipantController::class, 'indexInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::get('/export-rsvp', [MeetingParticipantController::class, 'exportRsvpInMeeting'])->middleware('can:operate,meeting');
    Route::patch('/{meetingParticipant}/respond', [MeetingParticipantController::class, 'respondInMeeting'])->middleware('can:respond,meetingParticipant');
});

// Tab 6 Ghi chú cá nhân — đại biểu/chair/op CRUD note của chính mình. Service auto-filter
// theo user_id; Policy owner-only cho show/update/delete (chair/op KHÔNG xem note người khác).
Route::prefix('{meeting}/personal-notes')->group(function () {
    Route::get('/', [MeetingPersonalNoteController::class, 'indexInMeeting'])->middleware('can:viewParticipant,meeting');
    Route::post('/', [MeetingPersonalNoteController::class, 'storeInMeeting'])->middleware('can:participate,meeting');
    Route::patch('/reorder', [MeetingPersonalNoteController::class, 'reorderInMeeting'])->middleware('can:participate,meeting');
    Route::get('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'showInMeeting'])->middleware('can:view,meetingPersonalNote');
    Route::put('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'updateInMeeting'])->middleware('can:update,meetingPersonalNote');
    Route::patch('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'updateInMeeting'])->middleware('can:update,meetingPersonalNote');
    Route::delete('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'destroyInMeeting'])->middleware('can:delete,meetingPersonalNote');
});

// Attachments nested 2 cấp: meeting → note → attachments. Owner of note gate cho mọi action.
Route::prefix('{meeting}/personal-notes/{meetingPersonalNote}/attachments')->group(function () {
    Route::post('/', [MeetingPersonalNoteAttachmentController::class, 'storeInNote'])->middleware('can:update,meetingPersonalNote');
    Route::patch('/reorder', [MeetingPersonalNoteAttachmentController::class, 'reorderInNote'])->middleware('can:update,meetingPersonalNote');
    Route::delete('/{meetingPersonalNoteAttachment}', [MeetingPersonalNoteAttachmentController::class, 'destroyInNote'])->middleware('can:delete,meetingPersonalNoteAttachment');
});
