<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentItemTypeController::class, 'export'])->middleware('permission:task-assignment-item-types.export,web');
Route::post('/import', [TaskAssignmentItemTypeController::class, 'import'])->middleware('permission:task-assignment-item-types.import,web');
Route::get('/import-template', [TaskAssignmentItemTypeController::class, 'importTemplate'])->middleware('permission:task-assignment-item-types.import,web');
Route::delete('/bulk-delete', [TaskAssignmentItemTypeController::class, 'bulkDestroy'])->middleware('permission:task-assignment-item-types.destroy,web');
Route::patch('/bulk-status', [TaskAssignmentItemTypeController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-item-types.update,web');
Route::get('/stats', [TaskAssignmentItemTypeController::class, 'stats'])->middleware('permission:task-assignment-item-types.index,web');
Route::get('/', [TaskAssignmentItemTypeController::class, 'index'])->middleware('permission:task-assignment-item-types.index,web');
Route::get('/{taskAssignmentItemType}', [TaskAssignmentItemTypeController::class, 'show'])->middleware('permission:task-assignment-item-types.index,web');
Route::post('/', [TaskAssignmentItemTypeController::class, 'store'])->middleware('permission:task-assignment-item-types.store,web');
Route::put('/{taskAssignmentItemType}', [TaskAssignmentItemTypeController::class, 'update'])->middleware('permission:task-assignment-item-types.update,web');
Route::patch('/{taskAssignmentItemType}', [TaskAssignmentItemTypeController::class, 'update'])->middleware('permission:task-assignment-item-types.update,web');
Route::delete('/{taskAssignmentItemType}', [TaskAssignmentItemTypeController::class, 'destroy'])->middleware('permission:task-assignment-item-types.destroy,web');
Route::patch('/{taskAssignmentItemType}/status', [TaskAssignmentItemTypeController::class, 'changeStatus'])->middleware('permission:task-assignment-item-types.update,web');
