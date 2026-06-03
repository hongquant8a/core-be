<?php

use App\Modules\Scheduling\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/stats',        [ScheduleController::class, 'stats'])->middleware('permission:schedules.stats,web');
Route::get('/weekly-matrix',  [ScheduleController::class, 'weeklyMatrix'])->middleware('permission:schedules.index,web');
Route::get('/weeks',        [ScheduleController::class, 'weeks'])->middleware('permission:schedules.index,web');
Route::get('/export',       [ScheduleController::class, 'export'])->middleware('permission:schedules.export,web');
Route::get('/export-pdf',   [ScheduleController::class, 'exportPdf'])->middleware('permission:schedules.export,web');
Route::get('/export-word',  [ScheduleController::class, 'exportWord'])->middleware('permission:schedules.export,web');

// Driver views - authentication checks done at API group level
Route::get('/driver-view',  [ScheduleController::class, 'driverIndex']);
Route::get('/driver-view/{schedule}', [ScheduleController::class, 'driverShow']);

Route::delete('/bulk-delete',[ScheduleController::class, 'bulkDestroy'])->middleware('permission:schedules.destroy,web');
Route::patch('/bulk-status', [ScheduleController::class, 'bulkUpdateStatus'])->middleware('permission:schedules.update,web');
Route::patch('/reorder',    [ScheduleController::class, 'reorder'])->middleware('permission:schedules.update,web');
Route::get('/',             [ScheduleController::class, 'index'])->middleware('permission:schedules.index,web');
Route::post('/',            [ScheduleController::class, 'store'])->middleware('permission:schedules.store,web');
Route::get('/{schedule}',   [ScheduleController::class, 'show'])->middleware('permission:schedules.show,web');
Route::put('/{schedule}',   [ScheduleController::class, 'update'])->middleware('can:update,schedule');
Route::patch('/{schedule}', [ScheduleController::class, 'update'])->middleware('can:update,schedule');
Route::delete('/{schedule}',[ScheduleController::class, 'destroy'])->middleware('can:delete,schedule');
Route::patch('/{schedule}/status',   [ScheduleController::class, 'changeStatus'])->middleware('permission:schedules.changeStatus,web');
Route::patch('/{schedule}/approve',  [ScheduleController::class, 'approve'])->middleware('permission:schedules.approve,web');
Route::patch('/{schedule}/reject',   [ScheduleController::class, 'reject'])->middleware('permission:schedules.approve,web');
Route::post('/{schedule}/duplicate', [ScheduleController::class, 'duplicate'])->middleware('permission:schedules.store,web');
