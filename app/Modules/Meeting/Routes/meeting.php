<?php

use App\Modules\Meeting\MeetingController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingController::class, 'bulkDestroy'])->middleware('permission:meetings.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingController::class, 'bulkUpdateStatus'])->middleware('permission:meetings.bulkUpdateStatus,web');
Route::get('/stats', [MeetingController::class, 'stats'])->middleware('permission:meetings.stats,web');
Route::get('/', [MeetingController::class, 'index'])->middleware('permission:meetings.index,web');
Route::get('/{meeting}', [MeetingController::class, 'show'])->middleware('permission:meetings.show,web');
Route::post('/', [MeetingController::class, 'store'])->middleware('permission:meetings.store,web');
Route::put('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::patch('/{meeting}', [MeetingController::class, 'update'])->middleware('permission:meetings.update,web');
Route::delete('/{meeting}', [MeetingController::class, 'destroy'])->middleware('permission:meetings.destroy,web');
Route::patch('/{meeting}/status', [MeetingController::class, 'changeStatus'])->middleware('permission:meetings.changeStatus,web');
