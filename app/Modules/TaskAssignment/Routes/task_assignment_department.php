<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentDepartmentController::class, 'export'])->middleware('permission:task-assignment-departments.export,web');
Route::post('/import', [TaskAssignmentDepartmentController::class, 'import'])->middleware('permission:task-assignment-departments.import,web');
Route::get('/import-template', [TaskAssignmentDepartmentController::class, 'importTemplate'])->middleware('permission:task-assignment-departments.import,web');
Route::delete('/bulk-delete', [TaskAssignmentDepartmentController::class, 'bulkDestroy'])->middleware('permission:task-assignment-departments.destroy,web');
Route::patch('/bulk-status', [TaskAssignmentDepartmentController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-departments.update,web');
Route::get('/stats', [TaskAssignmentDepartmentController::class, 'stats'])->middleware('permission:task-assignment-departments.index,web');
// Dropdown phòng ban — authenticated, KHÔNG qua Spatie permission, dành riêng cho form
// (giao task, gán nhân viên, ...). Tách khỏi `/public-options` (guest) và `/` (admin có Spatie).
Route::get('/options', [TaskAssignmentDepartmentController::class, 'options']);
Route::get('/', [TaskAssignmentDepartmentController::class, 'index'])->middleware('permission:task-assignment-departments.index,web');
Route::get('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'show'])->middleware('permission:task-assignment-departments.index,web');
Route::post('/', [TaskAssignmentDepartmentController::class, 'store'])->middleware('permission:task-assignment-departments.store,web');
Route::put('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'update'])->middleware('permission:task-assignment-departments.update,web');
Route::patch('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'update'])->middleware('permission:task-assignment-departments.update,web');
Route::delete('/{taskAssignmentDepartment}', [TaskAssignmentDepartmentController::class, 'destroy'])->middleware('permission:task-assignment-departments.destroy,web');
Route::patch('/{taskAssignmentDepartment}/status', [TaskAssignmentDepartmentController::class, 'changeStatus'])->middleware('permission:task-assignment-departments.update,web');
// Lookup user trong phòng ban — không qua Spatie permission để mọi authenticated user gọi được
// (dropdown điều chuyển task). FE không show menu quản lý chỉ vì có perm này.
Route::get('/{taskAssignmentDepartment}/users', [TaskAssignmentDepartmentController::class, 'users']);
Route::post('/{taskAssignmentDepartment}/users', [TaskAssignmentDepartmentController::class, 'syncUsers'])->middleware('permission:task-assignment-departments.syncUsers,web');
Route::delete('/{taskAssignmentDepartment}/users/{userId}', [TaskAssignmentDepartmentController::class, 'removeUser'])->middleware('permission:task-assignment-departments.removeUser,web');
