<?php

use App\Modules\Meeting\MeetingAttendeeController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingAttendeeController::class, 'bulkDestroy'])->middleware('permission:meeting-attendees.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingAttendeeController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-attendees.bulkUpdateStatus,web');
Route::get('/export', [MeetingAttendeeController::class, 'export'])->middleware('permission:meeting-attendees.export,web');
Route::post('/import', [MeetingAttendeeController::class, 'import'])->middleware('permission:meeting-attendees.import,web');
Route::get('/import-template', [MeetingAttendeeController::class, 'importTemplate'])->middleware('permission:meeting-attendees.import,web');
Route::get('/stats', [MeetingAttendeeController::class, 'stats'])->middleware('permission:meeting-attendees.stats,web');
Route::get('/user-options', [MeetingAttendeeController::class, 'userOptions'])->middleware('permission:meeting-attendees.store,web');
Route::get('/', [MeetingAttendeeController::class, 'index'])->middleware('permission:meeting-attendees.index,web');
Route::get('/{meetingAttendee}', [MeetingAttendeeController::class, 'show'])->middleware('permission:meeting-attendees.show,web');
Route::post('/', [MeetingAttendeeController::class, 'store'])->middleware('permission:meeting-attendees.store,web');
Route::put('/{meetingAttendee}', [MeetingAttendeeController::class, 'update'])->middleware('permission:meeting-attendees.update,web');
Route::patch('/{meetingAttendee}', [MeetingAttendeeController::class, 'update'])->middleware('permission:meeting-attendees.update,web');
Route::delete('/{meetingAttendee}', [MeetingAttendeeController::class, 'destroy'])->middleware('permission:meeting-attendees.destroy,web');
Route::patch('/{meetingAttendee}/status', [MeetingAttendeeController::class, 'changeStatus'])->middleware('permission:meeting-attendees.changeStatus,web');
