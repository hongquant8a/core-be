<?php

use App\Modules\Core\NotificationConfigController;
use App\Modules\Core\NotificationLogController;
use Illuminate\Support\Facades\Route;

/**
 * Routes notification scoped cho module Scheduling (Lịch công tác).
 * - Event configs + schedules (admin config theo org).
 * - Logs + stats (lịch sử gửi chỉ trong events của module + org hiện tại).
 * Middleware `notification.module:scheduling` set module_key vào request context.
 */
Route::middleware('notification.module:scheduling')->group(function () {
    // Admin event config
    Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
        ->middleware('permission:notifications.event-configs.index,web');
    Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
        ->middleware('permission:notifications.event-configs.update,web');

    Route::get('/event-configs/{eventKey}/schedules', [NotificationConfigController::class, 'scheduleIndex'])
        ->middleware('permission:notifications.schedules.index,web');
    Route::post('/event-configs/{eventKey}/schedules', [NotificationConfigController::class, 'scheduleStore'])
        ->middleware('permission:notifications.schedules.store,web');

    // Admin logs
    Route::get('/logs/stats', [NotificationLogController::class, 'stats'])
        ->middleware('permission:notifications.logs.index,web');
    Route::get('/logs/export', [NotificationLogController::class, 'export'])
        ->middleware('permission:notifications.logs.export,web');
    Route::delete('/logs/bulk-delete', [NotificationLogController::class, 'bulkDestroy'])
        ->middleware('permission:notifications.logs.bulkDestroy,web');
    Route::get('/logs', [NotificationLogController::class, 'index'])
        ->middleware('permission:notifications.logs.index,web');
    Route::get('/logs/{id}', [NotificationLogController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:notifications.logs.show,web');
    Route::delete('/logs/{id}', [NotificationLogController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permission:notifications.logs.destroy,web');
});
