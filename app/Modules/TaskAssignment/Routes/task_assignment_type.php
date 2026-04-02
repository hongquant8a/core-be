<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentTypeController::class, 'export'])->middleware('permission:task-assignment-types.export,web');
Route::post('/import', [TaskAssignmentTypeController::class, 'import'])->middleware('permission:task-assignment-types.import,web');
Route::post('/bulk-delete', [TaskAssignmentTypeController::class, 'bulkDestroy'])->middleware('permission:task-assignment-types.bulkDestroy,web');
Route::patch('/bulk-status', [TaskAssignmentTypeController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-types.bulkUpdateStatus,web');
Route::get('/stats', [TaskAssignmentTypeController::class, 'stats'])->middleware('permission:task-assignment-types.stats,web');
Route::get('/', [TaskAssignmentTypeController::class, 'index'])->middleware('permission:task-assignment-types.index,web');
Route::get('/{taskAssignmentType}', [TaskAssignmentTypeController::class, 'show'])->middleware('permission:task-assignment-types.show,web');
Route::post('/', [TaskAssignmentTypeController::class, 'store'])->middleware('permission:task-assignment-types.store,web');
Route::put('/{taskAssignmentType}', [TaskAssignmentTypeController::class, 'update'])->middleware('permission:task-assignment-types.update,web');
Route::patch('/{taskAssignmentType}', [TaskAssignmentTypeController::class, 'update'])->middleware('permission:task-assignment-types.update,web');
Route::delete('/{taskAssignmentType}', [TaskAssignmentTypeController::class, 'destroy'])->middleware('permission:task-assignment-types.destroy,web');
Route::patch('/{taskAssignmentType}/status', [TaskAssignmentTypeController::class, 'changeStatus'])->middleware('permission:task-assignment-types.changeStatus,web');
