<?php

use App\Modules\Meeting\MeetingDiscussionRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingDiscussionRegistrationController::class, 'bulkDestroy'])->middleware('permission:meeting-discussion-registrations.bulkDestroy,web');
Route::patch('/reorder', [MeetingDiscussionRegistrationController::class, 'reorder'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::get('/stats', [MeetingDiscussionRegistrationController::class, 'stats'])->middleware('permission:meeting-discussion-registrations.stats,web');
Route::get('/', [MeetingDiscussionRegistrationController::class, 'index'])->middleware('permission:meeting-discussion-registrations.index,web');
Route::get('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'show'])->middleware('permission:meeting-discussion-registrations.show,web');
Route::post('/', [MeetingDiscussionRegistrationController::class, 'store'])->middleware('permission:meeting-discussion-registrations.store,web');
Route::put('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::patch('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'update'])->middleware('permission:meeting-discussion-registrations.update,web');
Route::delete('/{meetingDiscussionRegistration}', [MeetingDiscussionRegistrationController::class, 'destroy'])->middleware('permission:meeting-discussion-registrations.destroy,web');
