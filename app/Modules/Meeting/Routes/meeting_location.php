<?php

use App\Modules\Meeting\MeetingLocationController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingLocationController::class, 'bulkDestroy'])->middleware('permission:meeting-locations.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingLocationController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-locations.bulkUpdateStatus,web');
Route::get('/stats', [MeetingLocationController::class, 'stats'])->middleware('permission:meeting-locations.stats,web');
Route::get('/', [MeetingLocationController::class, 'index'])->middleware('permission:meeting-locations.index,web');
Route::get('/{meetingLocation}', [MeetingLocationController::class, 'show'])->middleware('permission:meeting-locations.show,web');
Route::post('/', [MeetingLocationController::class, 'store'])->middleware('permission:meeting-locations.store,web');
Route::put('/{meetingLocation}', [MeetingLocationController::class, 'update'])->middleware('permission:meeting-locations.update,web');
Route::patch('/{meetingLocation}', [MeetingLocationController::class, 'update'])->middleware('permission:meeting-locations.update,web');
Route::delete('/{meetingLocation}', [MeetingLocationController::class, 'destroy'])->middleware('permission:meeting-locations.destroy,web');
Route::patch('/{meetingLocation}/status', [MeetingLocationController::class, 'changeStatus'])->middleware('permission:meeting-locations.changeStatus,web');
