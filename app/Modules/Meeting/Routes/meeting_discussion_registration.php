<?php

use App\Modules\Meeting\MeetingDiscussionRegistrationController;
use Illuminate\Support\Facades\Route;

// Export — auth-only, không Spatie permission. Gate qua MeetingPolicy::operate (chair/operator).
Route::get('/export', [MeetingDiscussionRegistrationController::class, 'export']);

Route::patch('/reorder', [MeetingDiscussionRegistrationController::class, 'reorder'])->middleware('permission:meeting-discussion-registrations.update,web');
// Operator đánh dấu hoàn thành (registered -> completed). Sprint 1 sẽ wrap thêm middleware meeting.role.
Route::patch('/{meetingDiscussionRegistration}/complete', [MeetingDiscussionRegistrationController::class, 'complete'])->middleware('permission:meeting-discussion-registrations.complete,web');
Route::get('/stats', [MeetingDiscussionRegistrationController::class, 'stats'])->middleware('permission:meeting-discussion-registrations.stats,web');
Route::get('/', [MeetingDiscussionRegistrationController::class, 'index'])->middleware('permission:meeting-discussion-registrations.index,web');
Route::get('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'show'])->middleware('permission:meeting-discussion-registrations.show,web');
Route::post('/', [MeetingDiscussionRegistrationController::class, 'store'])->middleware('permission:meeting-discussion-registrations.store,web');
Route::put('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::patch('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::delete('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'destroy'])->middleware('permission:meeting-discussion-registrations.destroy,web');
