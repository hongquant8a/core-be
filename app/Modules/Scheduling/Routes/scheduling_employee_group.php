<?php

use App\Modules\Scheduling\Controllers\SchedulingEmployeeGroupController;
use Illuminate\Support\Facades\Route;

// Dropdown options (public for module users)
Route::get('/options', [SchedulingEmployeeGroupController::class, 'options']);

// Stats endpoint
Route::get('/stats', [SchedulingEmployeeGroupController::class, 'stats']);

// Bulk actions
Route::delete('/bulk-delete', [SchedulingEmployeeGroupController::class, 'bulkDestroy']);
Route::patch('/bulk-status', [SchedulingEmployeeGroupController::class, 'bulkUpdateStatus']);

// Single status change
Route::patch('/{schedulingEmployeeGroup}/status', [SchedulingEmployeeGroupController::class, 'changeStatus']);

// Standard CRUD resource
Route::get('/', [SchedulingEmployeeGroupController::class, 'index']);
Route::post('/', [SchedulingEmployeeGroupController::class, 'store']);
Route::get('/{schedulingEmployeeGroup}', [SchedulingEmployeeGroupController::class, 'show']);
Route::put('/{schedulingEmployeeGroup}', [SchedulingEmployeeGroupController::class, 'update']);
Route::delete('/{schedulingEmployeeGroup}', [SchedulingEmployeeGroupController::class, 'destroy']);
