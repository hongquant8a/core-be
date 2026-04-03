<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentDepartmentController::class, 'export'])->middleware('permission:task-assignment-departments.export,web');
Route::post('/import', [TaskAssignmentDepartmentController::class, 'import'])->middleware('permission:task-assignment-departments.import,web');
Route::get('/import-template', [TaskAssignmentDepartmentController::class, 'importTemplate'])->middleware('permission:task-assignment-departments.import,web');
Route::post('/bulk-delete', [TaskAssignmentDepartmentController::class, 'bulkDestroy'])->middleware('permission:task-assignment-departments.bulkDestroy,web');
Route::patch('/bulk-status', [TaskAssignmentDepartmentController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-departments.bulkUpdateStatus,web');
Route::get('/stats', [TaskAssignmentDepartmentController::class, 'stats'])->middleware('permission:task-assignment-departments.stats,web');
Route::get('/', [TaskAssignmentDepartmentController::class, 'index'])->middleware('permission:task-assignment-departments.index,web');
Route::get('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'show'])->middleware('permission:task-assignment-departments.show,web');
Route::post('/', [TaskAssignmentDepartmentController::class, 'store'])->middleware('permission:task-assignment-departments.store,web');
Route::put('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'update'])->middleware('permission:task-assignment-departments.update,web');
Route::patch('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'update'])->middleware('permission:task-assignment-departments.update,web');
Route::delete('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'destroy'])->middleware('permission:task-assignment-departments.destroy,web');
Route::patch('/{taskAssignmentDepartment}/status', [TaskAssignmentDepartmentController::class, 'changeStatus'])->middleware('permission:task-assignment-departments.changeStatus,web');
