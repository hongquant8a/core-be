<?php

use App\Modules\Scheduling\Controllers\SchedulingEmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/stats',          [SchedulingEmployeeController::class, 'stats'])->middleware('permission:scheduling-employees.stats,web');
Route::get('/options',        [SchedulingEmployeeController::class, 'options']);
Route::get('/export',         [SchedulingEmployeeController::class, 'export'])->middleware('permission:scheduling-employees.export,web');
Route::post('/import',        [SchedulingEmployeeController::class, 'import'])->middleware('permission:scheduling-employees.import,web');
Route::delete('/bulk-delete', [SchedulingEmployeeController::class, 'bulkDestroy'])->middleware('permission:scheduling-employees.destroy,web');
Route::patch('/bulk-status',  [SchedulingEmployeeController::class, 'bulkUpdateStatus'])->middleware('permission:scheduling-employees.update,web');
Route::get('/',               [SchedulingEmployeeController::class, 'index'])->middleware('permission:scheduling-employees.index,web');
Route::post('/',              [SchedulingEmployeeController::class, 'store'])->middleware('permission:scheduling-employees.store,web');
Route::get('/{schedulingEmployee}',   [SchedulingEmployeeController::class, 'show'])->middleware('permission:scheduling-employees.show,web');
Route::put('/{schedulingEmployee}',   [SchedulingEmployeeController::class, 'update'])->middleware('permission:scheduling-employees.update,web');
Route::patch('/{schedulingEmployee}', [SchedulingEmployeeController::class, 'update'])->middleware('permission:scheduling-employees.update,web');
Route::delete('/{schedulingEmployee}',[SchedulingEmployeeController::class, 'destroy'])->middleware('permission:scheduling-employees.destroy,web');
Route::patch('/{schedulingEmployee}/status', [SchedulingEmployeeController::class, 'changeStatus'])->middleware('permission:scheduling-employees.changeStatus,web');
Route::post('/{schedulingEmployee}/sync-groups', [SchedulingEmployeeController::class, 'syncGroups'])->middleware('permission:scheduling-employees.update,web');
