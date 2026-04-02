<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentItemController::class, 'export'])->middleware('permission:task-assignment-items.export,web');
Route::post('/import', [TaskAssignmentItemController::class, 'import'])->middleware('permission:task-assignment-items.import,web');
Route::patch('/bulk-status', [TaskAssignmentItemController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-items.bulkUpdateStatus,web');
Route::post('/bulk-delete', [TaskAssignmentItemController::class, 'bulkDestroy'])->middleware('permission:task-assignment-items.bulkDestroy,web');
Route::get('/stats', [TaskAssignmentItemController::class, 'stats'])->middleware('permission:task-assignment-items.stats,web');
Route::get('/', [TaskAssignmentItemController::class, 'index'])->middleware('permission:task-assignment-items.index,web');
Route::get('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'show'])->middleware('permission:task-assignment-items.show,web');
Route::post('/', [TaskAssignmentItemController::class, 'store'])->middleware('permission:task-assignment-items.store,web');
Route::put('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('permission:task-assignment-items.update,web');
Route::patch('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('permission:task-assignment-items.update,web');
Route::delete('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'destroy'])->middleware('permission:task-assignment-items.destroy,web');
Route::patch('/{taskAssignmentItem}/progress', [TaskAssignmentItemController::class, 'updateProgress'])->middleware('permission:task-assignment-items.updateProgress,web');
Route::patch('/{taskAssignmentItem}/status', [TaskAssignmentItemController::class, 'changeStatus'])->middleware('permission:task-assignment-items.changeStatus,web');
