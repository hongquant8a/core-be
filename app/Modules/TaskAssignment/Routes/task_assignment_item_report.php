<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentItemReportController::class, 'index'])->middleware('permission:task-assignment-item-reports.index|my-received-tasks.report,web');
Route::post('/', [TaskAssignmentItemReportController::class, 'store'])->middleware('permission:task-assignment-item-reports.store|my-received-tasks.report,web');
Route::get('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'show'])->middleware('permission:task-assignment-item-reports.show|my-received-tasks.report,web');
Route::put('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:task-assignment-item-reports.update|my-received-tasks.report,web');
Route::patch('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:task-assignment-item-reports.update|my-received-tasks.report,web');
Route::delete('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'destroy'])->middleware('permission:task-assignment-item-reports.destroy|my-received-tasks.report,web');
