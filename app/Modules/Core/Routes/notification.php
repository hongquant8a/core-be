<?php

use App\Modules\Core\MyNotificationController;
use App\Modules\Core\NotificationConfigController;
use App\Modules\Core\NotificationController;
use Illuminate\Support\Facades\Route;

// Test endpoint
Route::post('/test', [NotificationController::class, 'test'])
    ->middleware('permission:notifications.test,web');

// User-facing: chỉ thao tác notification của user đang đăng nhập
Route::get('/me', [MyNotificationController::class, 'index']);
Route::get('/me/unread-count', [MyNotificationController::class, 'unreadCount']);
Route::patch('/me/read-all', [MyNotificationController::class, 'markAllAsRead']);
Route::patch('/me/{id}/read', [MyNotificationController::class, 'markAsRead'])->whereNumber('id');
Route::delete('/me/{id}', [MyNotificationController::class, 'destroy'])->whereNumber('id');

// Admin overview registry (optional — dashboard tổng quan)
Route::get('/modules', [NotificationConfigController::class, 'modules'])
    ->middleware('permission:notifications.event-configs.index,web');

// Schedule update/delete by id (id unique, module gắn cứng trong record)
Route::put('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleUpdate'])
    ->middleware('permission:notifications.schedules.update,web');
Route::delete('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleDestroy'])
    ->middleware('permission:notifications.schedules.destroy,web');

// Note: module-scoped config (event-configs, schedules list/create) nằm trong
// route file của từng module — vd app/Modules/TaskAssignment/Routes/notification_config.php.
// Middleware `notification.module:{key}` auto set module_key cho controller.
