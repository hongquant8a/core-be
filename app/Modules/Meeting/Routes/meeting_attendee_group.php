<?php

use App\Modules\Meeting\Controllers\MeetingAttendeeGroupController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [MeetingAttendeeGroupController::class, 'bulkDestroy'])->middleware('permission:meeting-attendee-groups.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingAttendeeGroupController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-attendee-groups.bulkUpdateStatus,web');
Route::get('/export', [MeetingAttendeeGroupController::class, 'export'])->middleware('permission:meeting-attendee-groups.export,web');
Route::post('/import', [MeetingAttendeeGroupController::class, 'import'])->middleware('permission:meeting-attendee-groups.import,web');
Route::get('/import-template', [MeetingAttendeeGroupController::class, 'importTemplate'])->middleware('permission:meeting-attendee-groups.import,web');
Route::get('/stats', [MeetingAttendeeGroupController::class, 'stats'])->middleware('permission:meeting-attendee-groups.stats,web');
Route::get('/', [MeetingAttendeeGroupController::class, 'index'])->middleware('permission:meeting-attendee-groups.index,web');
Route::get('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'show'])->middleware('permission:meeting-attendee-groups.show,web');
Route::post('/', [MeetingAttendeeGroupController::class, 'store'])->middleware('permission:meeting-attendee-groups.store,web');
Route::put('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'update'])->middleware('permission:meeting-attendee-groups.update,web');
Route::patch('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'update'])->middleware('permission:meeting-attendee-groups.update,web');
Route::delete('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'destroy'])->middleware('permission:meeting-attendee-groups.destroy,web');
Route::patch('/{meetingAttendeeGroup}/status', [MeetingAttendeeGroupController::class, 'changeStatus'])->middleware('permission:meeting-attendee-groups.changeStatus,web');

// Pivot M-N: quản lý đại biểu trong nhóm — pattern y hệt TaskAssignmentDepartment users.
//   GET    /attendees         — list, mỗi row có {user_id, ...}
//   POST   /attendees          body { user_ids: [int] }  — sync mode, BE auto find-or-create attendee
//   DELETE /attendees/{userId} — gỡ user khỏi nhóm bằng user_id
Route::get('/{meetingAttendeeGroup}/attendees', [MeetingAttendeeGroupController::class, 'attendees'])->middleware('permission:meeting-attendee-groups.attendees,web');
Route::post('/{meetingAttendeeGroup}/attendees', [MeetingAttendeeGroupController::class, 'syncAttendees'])->middleware('permission:meeting-attendee-groups.attendees,web');
Route::delete('/{meetingAttendeeGroup}/attendees/{userId}', [MeetingAttendeeGroupController::class, 'removeAttendee'])->middleware('permission:meeting-attendee-groups.attendees,web');
