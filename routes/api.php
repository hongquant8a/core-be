<?php

use App\Modules\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Auth module - public routes (đăng nhập, quên mật khẩu, đặt lại mật khẩu)
Route::prefix('auth')->middleware('log.activity')->group(function () {
    require base_path('app/Modules/Auth/Routes/auth.php');
});

// Cấu hình công khai - không cần xác thực
Route::get('/settings/public', [\App\Modules\Core\SettingController::class, 'public'])->middleware('log.activity');
Route::get('/document-signers/public', [\App\Modules\Document\DocumentSignerController::class, 'public'])->middleware('log.activity');
Route::get('/document-signers/public-options', [\App\Modules\Document\DocumentSignerController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/document-fields/public', [\App\Modules\Document\DocumentFieldController::class, 'public'])->middleware('log.activity');
Route::get('/document-fields/public-options', [\App\Modules\Document\DocumentFieldController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/document-types/public', [\App\Modules\Document\DocumentTypeController::class, 'public'])->middleware('log.activity');
Route::get('/document-types/public-options', [\App\Modules\Document\DocumentTypeController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/issuing-levels/public', [\App\Modules\Document\IssuingLevelController::class, 'public'])->middleware('log.activity');
Route::get('/issuing-levels/public-options', [\App\Modules\Document\IssuingLevelController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/issuing-agencies/public', [\App\Modules\Document\IssuingAgencyController::class, 'public'])->middleware('log.activity');
Route::get('/issuing-agencies/public-options', [\App\Modules\Document\IssuingAgencyController::class, 'publicOptions'])->middleware('log.activity');
Route::get('/post-categories/public', [\App\Modules\Post\PostCategoryController::class, 'public'])->middleware('log.activity');
Route::get('/post-categories/public-options', [\App\Modules\Post\PostCategoryController::class, 'publicOptions'])->middleware('log.activity');
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
    Route::prefix('posts')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Post/Routes/post.php');
    });
    Route::prefix('post-categories')->group(function () {
        require base_path('app/Modules/Post/Routes/post_category.php');
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
    Route::prefix('documents')->middleware('ensure.route.org')->group(function () {
        require base_path('app/Modules/Document/Routes/document.php');
    });
    Route::prefix('document-types')->group(function () {
        require base_path('app/Modules/Document/Routes/document_type.php');
    });
    Route::prefix('issuing-agencies')->group(function () {
        require base_path('app/Modules/Document/Routes/issuing_agency.php');
    });
    Route::prefix('issuing-levels')->group(function () {
        require base_path('app/Modules/Document/Routes/issuing_level.php');
    });
    Route::prefix('document-signers')->group(function () {
        require base_path('app/Modules/Document/Routes/document_signer.php');
    });
    Route::prefix('document-fields')->group(function () {
        require base_path('app/Modules/Document/Routes/document_field.php');
    });
    Route::prefix('settings')->group(function () {
        require base_path('app/Modules/Core/Routes/setting.php');
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
