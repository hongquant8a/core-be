<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentNoteController;
use Illuminate\Support\Facades\Route;

// Gác bằng policy chứ không chỉ permission: `my-received-tasks.note` nằm trong
// bộ quyền mặc định của vai trò Nhân viên, chỉ gác permission là mọi nhân viên
// ghi chú được vào công việc bất kỳ.
Route::post('/', [TaskAssignmentNoteController::class, 'store'])->middleware('can:note,taskAssignmentItem');
