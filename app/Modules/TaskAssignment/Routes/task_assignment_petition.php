<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentPetitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/stats', [TaskAssignmentPetitionController::class, 'stats'])
        ->middleware('permission:task-assignment-petitions.index,web');
    Route::get('/', [TaskAssignmentPetitionController::class, 'index'])
        ->middleware('permission:task-assignment-petitions.index,web');
    Route::get('/export', [TaskAssignmentPetitionController::class, 'export'])
        ->middleware('permission:task-assignment-petitions.export,web');
    Route::get('/{petition}', [TaskAssignmentPetitionController::class, 'show'])
        ->whereNumber('petition')
        ->middleware('permission:task-assignment-petitions.show,web');
    Route::post('/', [TaskAssignmentPetitionController::class, 'store'])
        ->middleware('permission:task-assignment-petitions.store,web');
    Route::put('/{petition}', [TaskAssignmentPetitionController::class, 'update'])
        ->whereNumber('petition')
        ->middleware('permission:task-assignment-petitions.update,web');
    Route::delete('/bulk-delete', [TaskAssignmentPetitionController::class, 'bulkDestroy'])
        ->middleware('permission:task-assignment-petitions.bulkDestroy,web');
    Route::delete('/{petition}', [TaskAssignmentPetitionController::class, 'destroy'])
        ->whereNumber('petition')
        ->middleware('permission:task-assignment-petitions.destroy,web');
    Route::patch('/{petition}/status', [TaskAssignmentPetitionController::class, 'changeStatus'])
        ->whereNumber('petition')
        ->middleware('permission:task-assignment-petitions.changeStatus,web');
    Route::patch('/{petition}/progress', [TaskAssignmentPetitionController::class, 'updateProgress'])
        ->whereNumber('petition')
        ->middleware('permission:task-assignment-petitions.update,web');
});
