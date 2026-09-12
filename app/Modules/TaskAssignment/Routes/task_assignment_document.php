<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentDocumentController::class, 'export'])->middleware('permission:task-assignment-documents.export,web');
Route::patch('/bulk-status', [TaskAssignmentDocumentController::class, 'bulkUpdateStatus'])->middleware('permission:task-assignment-documents.bulkUpdateStatus,web');
// Thùng rác phải đứng TRƯỚC route `/{taskAssignmentDocument}`, nếu không "trash"
// bị nuốt làm id. Văn bản không có policy riêng nên gác bằng `permission:`, đúng
// khuôn các route còn lại của file này.
Route::get('/trash', [TaskAssignmentDocumentController::class, 'trash'])->middleware('permission:task-assignment-documents.viewTrash,web');
// `withTrashed()`: binding mặc định bỏ qua bản ghi đã xoá mềm nên thiếu nó là 404.
Route::patch('/{taskAssignmentDocument}/restore', [TaskAssignmentDocumentController::class, 'restore'])->withTrashed()->middleware('permission:task-assignment-documents.restore,web');
Route::delete('/bulk-delete', [TaskAssignmentDocumentController::class, 'bulkDestroy'])->middleware('permission:task-assignment-documents.bulkDestroy,web');
// AI: 10 lần/phút — mỗi lần gọi tốn 5-35 giây và tính phí theo token.
Route::post('/analyze', [TaskAssignmentDocumentController::class, 'analyze'])->middleware(['permission:task-assignment-documents.analyze,web', 'throttle:10,1']);
Route::get('/stats', [TaskAssignmentDocumentController::class, 'stats'])->middleware('permission:task-overview.index|presentation.index,web');
Route::get('/stats-by-time', [TaskAssignmentDocumentController::class, 'statsByTime'])->middleware('permission:task-overview.index,web');
Route::get('/', [TaskAssignmentDocumentController::class, 'index'])->middleware('permission:task-assignment-documents.index,web');
Route::get('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'show'])->middleware('permission:task-assignment-documents.index,web');
Route::post('/', [TaskAssignmentDocumentController::class, 'store'])->middleware('permission:task-assignment-documents.store,web');
Route::put('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'update'])->middleware('permission:task-assignment-documents.update,web');
Route::patch('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'update'])->middleware('permission:task-assignment-documents.update,web');
Route::delete('/{taskAssignmentDocument}', [TaskAssignmentDocumentController::class, 'destroy'])->middleware('permission:task-assignment-documents.destroy,web');
Route::patch('/{taskAssignmentDocument}/status', [TaskAssignmentDocumentController::class, 'changeStatus'])->middleware('permission:task-assignment-documents.update,web');
