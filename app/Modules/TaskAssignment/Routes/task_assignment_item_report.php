<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemReportController;
use Illuminate\Support\Facades\Route;

// `index` và `store` nhận id công việc từ query/body chứ không phải route param
// nên `can:` không bind được model — hai action đó tự gọi Gate::authorize() trong
// controller sau khi tra ra công việc.
Route::get('/', [TaskAssignmentItemReportController::class, 'index'])->middleware('permission:my-received-tasks.report,web');
Route::post('/', [TaskAssignmentItemReportController::class, 'store'])->middleware('permission:my-received-tasks.report,web');
Route::get('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'show'])->middleware('can:view,taskAssignmentItemReport');
// Ba route ghi gác bằng policy: quyền `my-received-tasks.report` mở cửa, còn
// sửa/xoá được báo cáo NÀO thì do TaskAssignmentItemReportPolicy quyết định
// (người nộp, hoặc người giao việc). Chỉ gác permission như 3 route đọc phía
// trên thì ai có quyền báo cáo cũng sửa/xoá được báo cáo của người khác.
Route::put('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('can:update,taskAssignmentItemReport');
Route::patch('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'update'])->middleware('can:update,taskAssignmentItemReport');
Route::delete('/{taskAssignmentItemReport}', [TaskAssignmentItemReportController::class, 'destroy'])->middleware('can:delete,taskAssignmentItemReport');
