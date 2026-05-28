<?php

use App\Modules\Meeting\Controllers\MeetingDiscussionRegistrationController;
use Illuminate\Support\Facades\Route;

// Export — auth-only, không Spatie permission. Gate qua MeetingPolicy::operate (chair/operator).
Route::get('/export', [MeetingDiscussionRegistrationController::class, 'export']);

// In-meeting action (reorder + complete) đã DROP — dùng nested route với Gate Policy:
//   PATCH /api/meetings/{meeting}/discussion-registrations/reorder
//   PATCH /api/meetings/{meeting}/discussion-registrations/{reg}/complete
Route::get('/stats', [MeetingDiscussionRegistrationController::class, 'stats'])->middleware('permission:meeting-discussion-registrations.stats,web');
Route::get('/', [MeetingDiscussionRegistrationController::class, 'index'])->middleware('permission:meeting-discussion-registrations.index,web');
Route::get('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'show'])->middleware('permission:meeting-discussion-registrations.show,web');
Route::post('/', [MeetingDiscussionRegistrationController::class, 'store'])->middleware('permission:meeting-discussion-registrations.store,web');
Route::put('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::patch('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::delete('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'destroy'])->middleware('permission:meeting-discussion-registrations.destroy,web');
