<?php

use App\Modules\Scheduling\Controllers\SchedulingEmployeeGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/stats',          [SchedulingEmployeeGroupController::class, 'stats'])->middleware('permission:scheduling-employee-groups.stats,web');
Route::get('/options',        [SchedulingEmployeeGroupController::class, 'options']);
Route::delete('/bulk-delete', [SchedulingEmployeeGroupController::class, 'bulkDestroy'])->middleware('permission:scheduling-employee-groups.destroy,web');
Route::patch('/bulk-status',  [SchedulingEmployeeGroupController::class, 'bulkUpdateStatus'])->middleware('permission:scheduling-employee-groups.update,web');
Route::get('/',               [SchedulingEmployeeGroupController::class, 'index'])->middleware('permission:scheduling-employee-groups.index,web');
Route::post('/',              [SchedulingEmployeeGroupController::class, 'store'])->middleware('permission:scheduling-employee-groups.store,web');
Route::get('/{schedulingEmployeeGroup}',            [SchedulingEmployeeGroupController::class, 'show'])->middleware('permission:scheduling-employee-groups.show,web');
Route::put('/{schedulingEmployeeGroup}',            [SchedulingEmployeeGroupController::class, 'update'])->middleware('permission:scheduling-employee-groups.update,web');
Route::patch('/{schedulingEmployeeGroup}',          [SchedulingEmployeeGroupController::class, 'update'])->middleware('permission:scheduling-employee-groups.update,web');
Route::delete('/{schedulingEmployeeGroup}',         [SchedulingEmployeeGroupController::class, 'destroy'])->middleware('permission:scheduling-employee-groups.destroy,web');
Route::patch('/{schedulingEmployeeGroup}/status',   [SchedulingEmployeeGroupController::class, 'changeStatus'])->middleware('permission:scheduling-employee-groups.changeStatus,web');
Route::post('/{schedulingEmployeeGroup}/sync-members', [SchedulingEmployeeGroupController::class, 'syncMembers'])->middleware('permission:scheduling-employee-groups.update,web');
