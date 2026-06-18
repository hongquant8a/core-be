<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentPetitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/available-departments', [TaskAssignmentPetitionController::class, 'availableDepartments']);
    Route::get('/stats', [TaskAssignmentPetitionController::class, 'stats'])
        ->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentPetition');
    Route::get('/', [TaskAssignmentPetitionController::class, 'index'])
        ->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentPetition');
    Route::get('/export', [TaskAssignmentPetitionController::class, 'export'])
        ->middleware('permission:task-assignment-petitions.export,web'); // Export không cần model instance
    Route::get('/{petition}', [TaskAssignmentPetitionController::class, 'show'])
        ->whereNumber('petition')
        ->middleware('can:view,petition');
    Route::post('/', [TaskAssignmentPetitionController::class, 'store'])
        ->middleware('can:create,\App\Modules\TaskAssignment\Models\TaskAssignmentPetition');
    Route::put('/{petition}', [TaskAssignmentPetitionController::class, 'update'])
        ->whereNumber('petition')
        ->middleware('can:update,petition');
    Route::delete('/bulk-delete', [TaskAssignmentPetitionController::class, 'bulkDestroy'])
        ->middleware('permission:task-assignment-petitions.bulkDestroy,web'); // Bulk delete không cần model instance
    Route::delete('/{petition}', [TaskAssignmentPetitionController::class, 'destroy'])
        ->whereNumber('petition')
        ->middleware('can:delete,petition');
    Route::patch('/{petition}/status', [TaskAssignmentPetitionController::class, 'changeStatus'])
        ->whereNumber('petition')
        ->middleware('can:changeStatus,petition');
    Route::patch('/{petition}/progress', [TaskAssignmentPetitionController::class, 'updateProgress'])
        ->whereNumber('petition')
        ->middleware('can:update,petition'); // updateProgress dùng chung quyền update
});
