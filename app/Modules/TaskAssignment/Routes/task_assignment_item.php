<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentItemController::class, 'export'])->middleware('permission:task-assignment-items.export,web');
Route::get('/export-monthly-report', [TaskAssignmentItemController::class, 'exportMonthlyReport'])->middleware('permission:task-assignment-items.exportMonthlyReport,web');
Route::patch('/bulk-status', [TaskAssignmentItemController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-items.bulkUpdateStatus,web');
Route::post('/bulk-delete', [TaskAssignmentItemController::class, 'bulkDestroy'])->middleware('permission:task-assignment-items.bulkDestroy,web');
Route::get('/stats', [TaskAssignmentItemController::class, 'stats'])->middleware('permission:task-assignment-items.stats,web');
Route::get('/stats-by-department', [TaskAssignmentItemController::class, 'statsByDepartment'])->middleware('permission:task-assignment-items.statsByDepartment,web');
Route::get('/stats-by-user', [TaskAssignmentItemController::class, 'statsByUser'])->middleware('permission:task-assignment-items.statsByUser,web');
Route::get('/stats-by-time', [TaskAssignmentItemController::class, 'statsByTime'])->middleware('permission:task-assignment-items.statsByTime,web');
Route::get('/stats-by-item-type', [TaskAssignmentItemController::class, 'statsByItemType'])->middleware('permission:task-assignment-items.statsByItemType,web');
Route::get('/stats-by-document', [TaskAssignmentItemController::class, 'statsByDocument'])->middleware('permission:task-assignment-items.statsByDocument,web');
Route::get('/overdue', [TaskAssignmentItemController::class, 'overdue'])->middleware('permission:task-assignment-items.overdue,web');
Route::get('/upcoming-deadline', [TaskAssignmentItemController::class, 'upcomingDeadline'])->middleware('permission:task-assignment-items.upcomingDeadline,web');
Route::get('/', [TaskAssignmentItemController::class, 'index'])->middleware('permission:task-assignment-items.index,web');
Route::get('/{taskAssignmentItem}/timeline', [TaskAssignmentItemController::class, 'timeline'])->middleware('permission:task-assignment-items.show,web');
Route::get('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'show'])->middleware('permission:task-assignment-items.show,web');
Route::post('/', [TaskAssignmentItemController::class, 'store'])->middleware('permission:task-assignment-items.store,web');
Route::put('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('permission:task-assignment-items.update,web');
Route::patch('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('permission:task-assignment-items.update,web');
Route::delete('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'destroy'])->middleware('permission:task-assignment-items.destroy,web');
Route::patch('/{taskAssignmentItem}/progress', [TaskAssignmentItemController::class, 'updateProgress'])->middleware('permission:task-assignment-items.updateProgress,web');
Route::patch('/{taskAssignmentItem}/status', [TaskAssignmentItemController::class, 'changeStatus'])->middleware('permission:task-assignment-items.changeStatus,web');
Route::patch('/{taskAssignmentItem}/mark-done', [TaskAssignmentItemController::class, 'markDone'])->middleware('permission:task-assignment-items.markDone,web');
