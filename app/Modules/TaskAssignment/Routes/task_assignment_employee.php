<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentEmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentEmployeeController::class, 'export'])->middleware('permission:task-assignment-employees.export,web');
Route::post('/import', [TaskAssignmentEmployeeController::class, 'import'])->middleware('permission:task-assignment-employees.import,web');
Route::get('/import-template', [TaskAssignmentEmployeeController::class, 'importTemplate'])->middleware('permission:task-assignment-employees.import,web');
Route::delete('/bulk-delete', [TaskAssignmentEmployeeController::class, 'bulkDestroy'])->middleware('permission:task-assignment-employees.bulkDestroy,web');
Route::patch('/bulk-status', [TaskAssignmentEmployeeController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-employees.bulkUpdateStatus,web');
Route::get('/stats', [TaskAssignmentEmployeeController::class, 'stats'])->middleware('permission:task-assignment-employees.stats,web');
// Options endpoint cho dropdown — không qua Spatie permission để mọi authenticated user gọi được
// (FE phòng ban + form giao việc dùng để chọn nhân viên). Đồng quan điểm với
// route /task-assignment-departments/{id}/users.
Route::get('/options', [TaskAssignmentEmployeeController::class, 'options']);
Route::get('/', [TaskAssignmentEmployeeController::class, 'index'])->middleware('permission:task-assignment-employees.index,web');
Route::get('/{taskAssignmentEmployee}', [TaskAssignmentEmployeeController::class, 'show'])->middleware('permission:task-assignment-employees.index,web');
Route::post('/', [TaskAssignmentEmployeeController::class, 'store'])->middleware('permission:task-assignment-employees.store,web');
Route::put('/{taskAssignmentEmployee}', [TaskAssignmentEmployeeController::class, 'update'])->middleware('permission:task-assignment-employees.update,web');
Route::patch('/{taskAssignmentEmployee}', [TaskAssignmentEmployeeController::class, 'update'])->middleware('permission:task-assignment-employees.update,web');
Route::delete('/{taskAssignmentEmployee}', [TaskAssignmentEmployeeController::class, 'destroy'])->middleware('permission:task-assignment-employees.destroy,web');
Route::patch('/{taskAssignmentEmployee}/status', [TaskAssignmentEmployeeController::class, 'changeStatus'])->middleware('permission:task-assignment-employees.changeStatus,web');
