<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentTransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentTransferController::class, 'index'])->middleware('permission:my-assigned-tasks.transfer|my-received-tasks.transfer,web');
// POST gác bằng policy: quyền mở cửa, còn điều chuyển được việc NÀO thì do
// TaskAssignmentItemPolicy::transfer quyết định (người giao hoặc người được
// giao). Chỉ gác permission như route GET thì ai có quyền điều chuyển cũng
// chuyển được việc của người khác — service có nhánh lấy assignee `main` khi
// người gọi không nằm trong pivot.
Route::post('/', [TaskAssignmentTransferController::class, 'store'])->middleware('can:transfer,taskAssignmentItem');
