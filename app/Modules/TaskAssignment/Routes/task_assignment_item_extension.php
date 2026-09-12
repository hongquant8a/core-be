<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentItemExtensionController;
use Illuminate\Support\Facades\Route;

// Đọc lịch sử: cả người xin lẫn người duyệt đều cần thấy.
Route::get('/', [TaskAssignmentItemExtensionController::class, 'index'])
    ->middleware('permission:my-received-tasks.requestExtension|my-assigned-tasks.approveExtension,web');

// Gửi yêu cầu — policy đòi người gọi phải nằm trong danh sách thực hiện.
Route::post('/', [TaskAssignmentItemExtensionController::class, 'store'])
    ->middleware('can:requestExtension,taskAssignmentItem');

// Duyệt và từ chối là HAI nghiệp vụ, không phải hai giá trị của một trường trạng
// thái — nên hai endpoint riêng theo tên nghiệp vụ, đúng tiền lệ `/mark-done`,
// `/reject`, `/reopen`, `/pause`, `/cancel` của chính module này (xem CLAUDE.md
// mục 3). Cả hai gác bằng cùng một policy: chỉ người đã giao việc.
Route::patch('/{extension}/approve', [TaskAssignmentItemExtensionController::class, 'approve'])
    ->middleware('can:approveExtension,taskAssignmentItem');
Route::patch('/{extension}/reject', [TaskAssignmentItemExtensionController::class, 'reject'])
    ->middleware('can:approveExtension,taskAssignmentItem');

// Thu hồi: gác bằng quyền gửi yêu cầu, còn "có phải yêu cầu của chính mình không"
// do service kiểm — nó là điều kiện trên bản ghi yêu cầu, không phải trên công việc.
Route::delete('/{extension}', [TaskAssignmentItemExtensionController::class, 'destroy'])
    ->middleware('can:requestExtension,taskAssignmentItem');
