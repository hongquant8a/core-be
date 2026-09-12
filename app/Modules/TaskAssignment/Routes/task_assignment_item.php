<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [TaskAssignmentItemController::class, 'export'])->middleware('permission:my-assigned-tasks.export|my-received-tasks.export,web');
Route::get('/export-monthly-report', [TaskAssignmentItemController::class, 'exportMonthlyReport'])->middleware('permission:task-overview.exportMonthlyReport,web');
Route::patch('/bulk-status', [TaskAssignmentItemController::class, 'bulkUpdateStatus'])->middleware('can:bulkUpdateStatus,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::delete('/bulk-delete', [TaskAssignmentItemController::class, 'bulkDestroy'])->middleware('can:bulkDestroy,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
// Đặt TRƯỚC route `/{taskAssignmentItem}` — nếu không sẽ bị nuốt thành id.
// Thùng rác đứng trước mọi route `/{taskAssignmentItem}` để không bị nuốt làm id.
Route::get('/trash', [TaskAssignmentItemController::class, 'trash'])->middleware('can:viewTrash,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::patch('/{taskAssignmentItem}/restore', [TaskAssignmentItemController::class, 'restore'])->withTrashed()->middleware('can:restore,taskAssignmentItem');
Route::get('/filter-options', [TaskAssignmentItemController::class, 'filterOptions'])->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::get('/stats', [TaskAssignmentItemController::class, 'stats'])->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::get('/stats-by-department', [TaskAssignmentItemController::class, 'statsByDepartment'])->middleware('permission:task-overview.index|presentation.index,web');
Route::get('/stats-by-user', [TaskAssignmentItemController::class, 'statsByUser'])->middleware('permission:task-overview.index,web');
Route::get('/stats-by-time', [TaskAssignmentItemController::class, 'statsByTime'])->middleware('permission:task-overview.index,web');
Route::get('/stats-by-item-type', [TaskAssignmentItemController::class, 'statsByItemType'])->middleware('permission:task-overview.index|presentation.index,web');
Route::get('/stats-by-document', [TaskAssignmentItemController::class, 'statsByDocument'])->middleware('permission:task-overview.index,web');
Route::get('/overdue', [TaskAssignmentItemController::class, 'overdue'])->middleware('permission:task-overview.index|presentation.index,web');
Route::get('/upcoming-deadline', [TaskAssignmentItemController::class, 'upcomingDeadline'])->middleware('permission:task-overview.index|presentation.index,web');
Route::get('/', [TaskAssignmentItemController::class, 'index'])->middleware('can:viewAny,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::get('/{taskAssignmentItem}/timeline', [TaskAssignmentItemController::class, 'timeline'])->middleware('can:view,taskAssignmentItem');
Route::get('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'show'])->middleware('can:view,taskAssignmentItem');
Route::post('/', [TaskAssignmentItemController::class, 'store'])->middleware('can:create,\App\Modules\TaskAssignment\Models\TaskAssignmentItem');
Route::put('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('can:update,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'update'])->middleware('can:update,taskAssignmentItem');
Route::delete('/{taskAssignmentItem}', [TaskAssignmentItemController::class, 'destroy'])->middleware('can:delete,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/progress', [TaskAssignmentItemController::class, 'updateProgress'])->middleware('can:updateProgress,taskAssignmentItem');
// Bốn thao tác can thiệp = bốn endpoint, mỗi cái một quyền riêng. Trước đây tạm
// dừng và huỷ dồn vào `/status`, policy OR ba quyền rồi thò tay đọc
// `processing_status` trong request để đoán — nên `pause`/`cancel` là quyền chết:
// có một trong ba là làm được cả ba.
// `/status` nay chỉ còn dùng để chuyển giữa Chưa thực hiện ↔ Đang thực hiện.
Route::patch('/{taskAssignmentItem}/status', [TaskAssignmentItemController::class, 'changeStatus'])->middleware('can:changeStatus,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/pause', [TaskAssignmentItemController::class, 'pause'])->middleware('can:pause,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/cancel', [TaskAssignmentItemController::class, 'cancel'])->middleware('can:cancel,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/mark-done', [TaskAssignmentItemController::class, 'markDone'])->middleware('can:markDone,taskAssignmentItem');
// Trả lại báo cáo và mở lại công việc là nghiệp vụ riêng, không phải đổi trạng
// thái chung: chúng là mặt còn lại của việc duyệt nên gác bằng policy riêng,
// chỉ người đã giao việc mới làm được — giống `mark-done`.
Route::patch('/{taskAssignmentItem}/reopen', [TaskAssignmentItemController::class, 'reopen'])->middleware('can:reopen,taskAssignmentItem');
Route::patch('/{taskAssignmentItem}/reject', [TaskAssignmentItemController::class, 'reject'])->middleware('can:reject,taskAssignmentItem');
