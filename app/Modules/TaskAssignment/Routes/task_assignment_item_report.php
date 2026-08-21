<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentItemReportController::class, 'index'])->middleware('permission:my-received-tasks.report,web');
Route::post('/', [TaskAssignmentItemReportController::class, 'store'])->middleware('permission:my-received-tasks.report,web');
Route::get('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'show'])->middleware('permission:my-received-tasks.report,web');
Route::put('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:my-received-tasks.report,web');
Route::patch('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:my-received-tasks.report,web');
Route::delete('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'destroy'])->middleware('permission:my-received-tasks.report,web');
