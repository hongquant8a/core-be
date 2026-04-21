<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentItemReportController::class, 'index'])->middleware('permission:task-assignment-item-reports.index,web');
Route::post('/', [TaskAssignmentItemReportController::class, 'store'])->middleware('permission:task-assignment-item-reports.store,web');
Route::get('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'show'])->middleware('permission:task-assignment-item-reports.show,web');
Route::put('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:task-assignment-item-reports.update,web');
Route::patch('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('permission:task-assignment-item-reports.update,web');
Route::delete('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'destroy'])->middleware('permission:task-assignment-item-reports.destroy,web');
Route::patch('/{id}/confirm', [TaskAssignmentItemReportController::class, 'confirm'])->middleware('permission:task-assignment-item-reports.confirm,web');
