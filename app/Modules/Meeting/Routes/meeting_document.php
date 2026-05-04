<?php

use App\Modules\Meeting\MeetingDocumentController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingDocumentController::class, 'bulkDestroy'])->middleware('permission:meeting-documents.bulkDestroy,web');
Route::patch('/bulk-status', [MeetingDocumentController::class, 'bulkUpdateStatus'])->middleware('permission:meeting-documents.bulkUpdateStatus,web');
Route::patch('/reorder', [MeetingDocumentController::class, 'reorder'])->middleware('permission:meeting-documents.update,web');
Route::get('/', [MeetingDocumentController::class, 'index'])->middleware('permission:meeting-documents.index,web');
Route::get('/{meetingDocument}', [MeetingDocumentController::class, 'show'])->middleware('permission:meeting-documents.show,web');
Route::post('/', [MeetingDocumentController::class, 'store'])->middleware('permission:meeting-documents.store,web');
Route::put('/{meetingDocument}', [MeetingDocumentController::class, 'update'])->middleware('permission:meeting-documents.update,web');
Route::patch('/{meetingDocument}', [MeetingDocumentController::class, 'update'])->middleware('permission:meeting-documents.update,web');
Route::delete('/{meetingDocument}', [MeetingDocumentController::class, 'destroy'])->middleware('permission:meeting-documents.destroy,web');
Route::patch('/{meetingDocument}/status', [MeetingDocumentController::class, 'changeStatus'])->middleware('permission:meeting-documents.changeStatus,web');
