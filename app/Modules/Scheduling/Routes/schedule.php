<?php

use App\Modules\Scheduling\Controllers\ScheduleController;
use App\Modules\Scheduling\Controllers\ScheduleApprovalController;
use App\Modules\Scheduling\Controllers\ScheduleReorderController;
use App\Modules\Scheduling\Controllers\ScheduleDuplicateController;
use App\Modules\Scheduling\Controllers\DriverScheduleController;
use App\Modules\Scheduling\Controllers\ScheduleExportController;
use Illuminate\Support\Facades\Route;

// Driver schedules routes
Route::get('/driver/my-schedules', [DriverScheduleController::class, 'index']);
Route::get('/driver/my-schedules/{id}', [DriverScheduleController::class, 'show']);

// Export routes
Route::get('/export/excel', [ScheduleExportController::class, 'excel']);
Route::get('/export/pdf', [ScheduleExportController::class, 'pdf']);
Route::get('/export/word', [ScheduleExportController::class, 'word']);


// Weekly Matrix
Route::get('/weekly-matrix', [ScheduleController::class, 'weeklyMatrix']);

// Reorder schedules
Route::post('/reorder', [ScheduleReorderController::class, 'reorder']);

// Duplicate schedule
Route::post('/{schedule}/duplicate', [ScheduleDuplicateController::class, 'duplicate']);

// Approve / Reject
Route::post('/{schedule}/approve', [ScheduleApprovalController::class, 'approve']);
Route::post('/{schedule}/reject', [ScheduleApprovalController::class, 'reject']);

// Restore soft deleted schedule
Route::post('/{id}/restore', [ScheduleController::class, 'restore']);

// Stats endpoint
Route::get('/stats', [ScheduleController::class, 'stats']);

// Bulk actions
Route::delete('/bulk-delete', [ScheduleController::class, 'bulkDestroy']);
Route::patch('/bulk-status', [ScheduleController::class, 'bulkUpdateStatus']);

// Single status change
Route::patch('/{schedule}/status', [ScheduleController::class, 'changeStatus']);

// Import routes
Route::post('/import', [ScheduleController::class, 'import']);
Route::get('/import-template', [ScheduleController::class, 'importTemplate']);

// Standard CRUD resource
Route::get('/', [ScheduleController::class, 'index']);
Route::post('/', [ScheduleController::class, 'store']);
Route::get('/{schedule}', [ScheduleController::class, 'show']);
Route::put('/{schedule}', [ScheduleController::class, 'update']);
Route::delete('/{schedule}', [ScheduleController::class, 'destroy']);
