<?php

use App\Modules\Meeting\MeetingTypeController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingTypeController::class, 'bulkDestroy'])->middleware('permission:meeting-types.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingTypeController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-types.bulkUpdateStatus,web');
Route::get('/stats', [MeetingTypeController::class, 'stats'])->middleware('permission:meeting-types.stats,web');
Route::get('/', [MeetingTypeController::class, 'index'])->middleware('permission:meeting-types.index,web');
Route::get('/{meetingType}', [MeetingTypeController::class, 'show'])->middleware('permission:meeting-types.show,web');
Route::post('/', [MeetingTypeController::class, 'store'])->middleware('permission:meeting-types.store,web');
Route::put('/{meetingType}', [MeetingTypeController::class, 'update'])->middleware('permission:meeting-types.update,web');
Route::patch('/{meetingType}', [MeetingTypeController::class, 'update'])->middleware('permission:meeting-types.update,web');
Route::delete('/{meetingType}', [MeetingTypeController::class, 'destroy'])->middleware('permission:meeting-types.destroy,web');
Route::patch('/{meetingType}/status', [MeetingTypeController::class, 'changeStatus'])->middleware('permission:meeting-types.changeStatus,web');
