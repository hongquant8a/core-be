<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentDocumentController::class, 'export'])->middleware('permission:task-assignment-documents.export,web');
Route::patch('/bulk-status', [TaskAssignmentDocumentController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-documents.bulkUpdateStatus,web');
Route::delete('/bulk-delete', [TaskAssignmentDocumentController::class, 'bulkDestroy'])->middleware('permission:task-assignment-documents.bulkDestroy,web');
Route::get('/stats', [TaskAssignmentDocumentController::class, 'stats'])->middleware('permission:task-assignment-documents.stats|presentation.index,web');
Route::get('/stats-by-time', [TaskAssignmentDocumentController::class, 'statsByTime'])->middleware('permission:task-assignment-documents.statsByTime,web');
Route::get('/', [TaskAssignmentDocumentController::class, 'index'])->middleware('permission:task-assignment-documents.index,web');
Route::get('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'show'])->middleware('permission:task-assignment-documents.show,web');
Route::post('/', [TaskAssignmentDocumentController::class, 'store'])->middleware('permission:task-assignment-documents.store,web');
Route::put('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'update'])->middleware('permission:task-assignment-documents.update,web');
Route::patch('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'update'])->middleware('permission:task-assignment-documents.update,web');
Route::delete('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'destroy'])->middleware('permission:task-assignment-documents.destroy,web');
Route::patch('/{taskAssignmentDocument}/status', [TaskAssignmentDocumentController::class, 'changeStatus'])->middleware('permission:task-assignment-documents.changeStatus,web');
