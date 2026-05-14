<?php

use App\Modules\Meeting\MeetingGuestController;
use Illuminate\Support\Facades\Route;

Route::delete('/bulk-delete', [MeetingGuestController::class, 'bulkDestroy'])->middleware('permission:meeting-guests.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingGuestController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-guests.bulkUpdateStatus,web');
Route::get('/export', [MeetingGuestController::class, 'export'])->middleware('permission:meeting-guests.export,web');
Route::post('/import', [MeetingGuestController::class, 'import'])->middleware('permission:meeting-guests.import,web');
Route::get('/import-template', [MeetingGuestController::class, 'importTemplate'])->middleware('permission:meeting-guests.import,web');
Route::get('/stats', [MeetingGuestController::class, 'stats'])->middleware('permission:meeting-guests.stats,web');
Route::get('/', [MeetingGuestController::class, 'index'])->middleware('permission:meeting-guests.index,web');
Route::get('/{meetingGuest}', [MeetingGuestController::class, 'show'])->middleware('permission:meeting-guests.show,web');
Route::post('/', [MeetingGuestController::class, 'store'])->middleware('permission:meeting-guests.store,web');
Route::put('/{meetingGuest}', [MeetingGuestController::class, 'update'])->middleware('permission:meeting-guests.update,web');
Route::patch('/{meetingGuest}', [MeetingGuestController::class, 'update'])->middleware('permission:meeting-guests.update,web');
Route::delete('/{meetingGuest}', [MeetingGuestController::class, 'destroy'])->middleware('permission:meeting-guests.destroy,web');
Route::patch('/{meetingGuest}/status', [MeetingGuestController::class, 'changeStatus'])->middleware('permission:meeting-guests.changeStatus,web');
