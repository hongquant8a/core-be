<?php

use App\Modules\Meeting\MeetingAttendeeGroupController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingAttendeeGroupController::class, 'bulkDestroy'])->middleware('permission:meeting-attendee-groups.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingAttendeeGroupController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-attendee-groups.bulkUpdateStatus,web');
Route::get('/stats', [MeetingAttendeeGroupController::class, 'stats'])->middleware('permission:meeting-attendee-groups.stats,web');
Route::get('/', [MeetingAttendeeGroupController::class, 'index'])->middleware('permission:meeting-attendee-groups.index,web');
Route::get('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'show'])->middleware('permission:meeting-attendee-groups.show,web');
Route::post('/', [MeetingAttendeeGroupController::class, 'store'])->middleware('permission:meeting-attendee-groups.store,web');
Route::put('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'update'])->middleware('permission:meeting-attendee-groups.update,web');
Route::patch('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'update'])->middleware('permission:meeting-attendee-groups.update,web');
Route::delete('/{meetingAttendeeGroup}', [MeetingAttendeeGroupController::class, 'destroy'])->middleware('permission:meeting-attendee-groups.destroy,web');
Route::patch('/{meetingAttendeeGroup}/status', [MeetingAttendeeGroupController::class, 'changeStatus'])->middleware('permission:meeting-attendee-groups.changeStatus,web');
