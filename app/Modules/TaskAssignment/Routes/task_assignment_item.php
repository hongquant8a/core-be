<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentItemController::class, 'export'])->middleware('permission:task-assignment-items.export,web');
// Sinh signed URL tạm thời (5 phút) để Zalo Mini App gọi downloadFile({url}) — native
// không đính kèm được Authorization header, chỉ nhận 1 URL. Xem route
// 'task-assignment-items.export.signed' (routes/api.php, nhóm public) — xác thực bằng
// chữ ký thay vì Bearer token, dùng lại nguyên action export() phía trên.
Route::get('/export-link', [TaskAssignmentItemController::class, 'exportLink'])->middleware('permission:task-assignment-items.export,web');
Route::get('/export-monthly-report', [TaskAssignmentItemController::class, 'exportMonthlyReport'])->middleware('permission:task-assignment-items.exportMonthlyReport,web');
Route::patch('/bulk-status', [TaskAssignmentItemController::class, 'bulkUpdateStatus'])->middleware('can:bulkUpdateStatus,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::delete('/bulk-delete', [TaskAssignmentItemController::class, 'bulkDestroy'])->middleware('can:bulkDestroy,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::get('/stats', [TaskAssignmentItemController::class, 'stats'])->middleware('permission:task-assignment-items.stats|presentation.index,web');
Route::get('/stats-by-department', [TaskAssignmentItemController::class, 'statsByDepartment'])->middleware('permission:task-assignment-items.statsByDepartment|presentation.index,web');
Route::get('/stats-by-user', [TaskAssignmentItemController::class, 'statsByUser'])->middleware('permission:task-assignment-items.statsByUser,web');
Route::get('/stats-by-time', [TaskAssignmentItemController::class, 'statsByTime'])->middleware('permission:task-assignment-items.statsByTime,web');
Route::get('/stats-by-item-type', [TaskAssignmentItemController::class, 'statsByItemType'])->middleware('permission:task-assignment-items.statsByItemType|presentation.index,web');
Route::get('/stats-by-document', [TaskAssignmentItemController::class, 'statsByDocument'])->middleware('permission:task-assignment-items.statsByDocument,web');
Route::get('/overdue', [TaskAssignmentItemController::class, 'overdue'])->middleware('permission:task-assignment-items.overdue|presentation.index,web');
Route::get('/upcoming-deadline', [TaskAssignmentItemController::class, 'upcomingDeadline'])->middleware('permission:task-assignment-items.upcomingDeadline|presentation.index,web');
Route::get('/', [TaskAssignmentItemController::class, 'index'])->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::get('/{taskAssignmentItem}/timeline', [TaskAssignmentItemController::class, 'timeline'])->middleware('can:view,taskAssignmentItem');
Route::get('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'show'])->middleware('can:view,taskAssignmentItem');
Route::post('/', [TaskAssignmentItemController::class, 'store'])->middleware('can:create,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::put('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('can:update,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('can:update,taskAssignmentItem');
Route::delete('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'destroy'])->middleware('can:delete,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/progress', [TaskAssignmentItemController::class, 'updateProgress'])->middleware('can:updateProgress,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/status', [TaskAssignmentItemController::class, 'changeStatus'])->middleware('can:changeStatus,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/mark-done', [TaskAssignmentItemController::class, 'markDone'])->middleware('can:markDone,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/reopen', [TaskAssignmentItemController::class, 'reopen'])->middleware('can:changeStatus,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/reject', [TaskAssignmentItemController::class, 'reject'])->middleware('can:changeStatus,taskAssignmentItem');
