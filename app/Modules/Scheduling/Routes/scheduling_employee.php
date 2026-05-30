<?php

use App\Modules\Scheduling\Controllers\SchedulingEmployeeController;
use Illuminate\Support\Facades\Route;

// Dropdown options (public for module users)
Route::get('/options', [SchedulingEmployeeController::class, 'options']);

// Stats endpoint
Route::get('/stats', [SchedulingEmployeeController::class, 'stats']);

// Bulk actions
Route::delete('/bulk-delete', [SchedulingEmployeeController::class, 'bulkDestroy']);
Route::patch('/bulk-status', [SchedulingEmployeeController::class, 'bulkUpdateStatus']);

// Single status change
Route::patch('/{schedulingEmployee}/status', [SchedulingEmployeeController::class, 'changeStatus']);

// Standard CRUD resource
Route::get('/', [SchedulingEmployeeController::class, 'index']);
Route::post('/', [SchedulingEmployeeController::class, 'store']);
Route::get('/{schedulingEmployee}', [SchedulingEmployeeController::class, 'show']);
Route::put('/{schedulingEmployee}', [SchedulingEmployeeController::class, 'update']);
Route::delete('/{schedulingEmployee}', [SchedulingEmployeeController::class, 'destroy']);
