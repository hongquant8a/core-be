<?php

use App\Modules\Scheduling\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/stats',        [ScheduleController::class, 'stats'])->middleware('schedule.module:stats');
Route::get('/weekly-matrix',  [ScheduleController::class, 'weeklyMatrix'])->middleware('schedule.module:index');
Route::get('/weeks',        [ScheduleController::class, 'weeks'])->middleware('schedule.module:index');
Route::get('/export',       [ScheduleController::class, 'export'])->middleware('schedule.module:export');
Route::get('/export-pdf',   [ScheduleController::class, 'exportPdf'])->middleware('schedule.module:export');
Route::get('/export-word',  [ScheduleController::class, 'exportWord'])->middleware('schedule.module:export');

// Driver views - authentication checks done at API group level
Route::get('/driver-view',  [ScheduleController::class, 'driverIndex']);
Route::get('/driver-view/{schedule}', [ScheduleController::class, 'driverShow']);

// General views cho toàn bộ nhân viên (cần Auth, nhưng không check permission quản trị)
Route::get('/general', [ScheduleController::class, 'general']);
Route::get('/general/weekly-matrix', [ScheduleController::class, 'generalWeeklyMatrix']);
Route::get('/general/weeks', [ScheduleController::class, 'generalWeeks']);
Route::get('/week-counts',  [ScheduleController::class, 'weekCounts']);

Route::delete('/bulk-delete',[ScheduleController::class, 'bulkDestroy'])->middleware('schedule.module:destroy');
Route::patch('/bulk-status', [ScheduleController::class, 'bulkUpdateStatus'])->middleware('schedule.module:update');
Route::patch('/reorder',    [ScheduleController::class, 'reorder'])->middleware('schedule.module:update');
Route::get('/',             [ScheduleController::class, 'index'])->middleware('schedule.module:index');
Route::post('/',            [ScheduleController::class, 'store'])->middleware('schedule.module:store');
Route::get('/{schedule}',   [ScheduleController::class, 'show'])->middleware('schedule.module:show');
Route::put('/{schedule}',   [ScheduleController::class, 'update'])->middleware('schedule.module:update', 'can:update,schedule');
Route::patch('/{schedule}', [ScheduleController::class, 'update'])->middleware('schedule.module:update', 'can:update,schedule');
Route::delete('/{schedule}',[ScheduleController::class, 'destroy'])->middleware('schedule.module:destroy', 'can:delete,schedule');
Route::patch('/{schedule}/status',   [ScheduleController::class, 'changeStatus'])->middleware('schedule.module:changeStatus');
Route::patch('/{schedule}/approve',  [ScheduleController::class, 'approve'])->middleware('schedule.module:approve');
Route::patch('/{schedule}/reject',   [ScheduleController::class, 'reject'])->middleware('schedule.module:approve');
Route::post('/{schedule}/duplicate', [ScheduleController::class, 'duplicate'])->middleware('schedule.module:store');
