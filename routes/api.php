<?php

use App\Modules\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Auth module - public routes (đăng nhập, quên mật khẩu, đặt lại mật khẩu)
Route::prefix('auth')->middleware('log.activity')->group(function () {
    require base_path('app/Modules/Auth/Routes/auth.php');
});

// Cấu hình công khai - không cần xác thực
Route::get('/settings/public', [\App\Modules\Core\SettingController::class, 'public'])->middleware('log.activity');
Route::get('/organizations/public', [\App\Modules\Core\OrganizationController::class, 'public'])->middleware('log.activity');
Route::get('/organizations/public-options', [\App\Modules\Core\OrganizationController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-types/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-types/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-item-types/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-item-types/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentItemTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/task-assignment-departments/public', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'public'])->middleware('log.activity');
Route::get('/task-assignment-departments/public-options', [\App\Modules\TaskAssignment\Controllers\TaskAssignmentDepartmentController::class, 'publicOptions'])->middleware('log.activity');

// Route yêu cầu đăng nhập (Bearer token) và đặt ngữ cảnh team cho Spatie Permission
Route::middleware(['auth:sanctum', 'set.permissions.team', 'log.activity'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::prefix('users')->group(function () {
        require base_path('app/Modules/Core/Routes/user.php');
    });
    Route::prefix('permissions')->group(function () {
        require base_path('app/Modules/Core/Routes/permission.php');
    });
    Route::prefix('roles')->group(function () {
        require base_path('app/Modules/Core/Routes/role.php');
    });
    Route::prefix('organizations')->group(function () {
        require base_path('app/Modules/Core/Routes/organization.php');
    });
    Route::prefix('log-activities')->group(function () {
        require base_path('app/Modules/Core/Routes/log_activity.php');
    });
    Route::prefix('settings')->group(function () {
        require base_path('app/Modules/Core/Routes/setting.php');
    });
    Route::prefix('notifications')->group(function () {
        require base_path('app/Modules/Core/Routes/notification.php');
    });

    // TaskAssignment module - không scope organization_id
    Route::prefix('task-assignment-departments')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_department.php');
    });
    Route::prefix('task-assignment-types')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_type.php');
    });
    Route::prefix('task-assignment-item-types')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item_type.php');
    });
    Route::prefix('task-assignment-documents')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_document.php');
    });
    Route::prefix('task-assignment-items')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item.php');
    });
    Route::prefix('task-assignment-item-reports')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item_report.php');
    });
});
