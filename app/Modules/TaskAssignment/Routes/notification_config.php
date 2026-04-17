<?php

use App\Modules\Core\NotificationConfigController;
use Illuminate\Support\Facades\Route;

/**
 * Routes cấu hình notification cho module TaskAssignment.
 * module_key được set bởi middleware `notification.module:task_assignment`.
 * Controller dùng chung (NotificationConfigController), FE không cần truyền module_key.
 */
Route::middleware('notification.module:task_assignment')->group(function () {
    Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
        ->middleware('permission:notifications.event-configs.index,web');
    Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
        ->middleware('permission:notifications.event-configs.update,web');

    Route::get('/schedules', [NotificationConfigController::class, 'scheduleIndex'])
        ->middleware('permission:notifications.schedules.index,web');
    Route::post('/schedules', [NotificationConfigController::class, 'scheduleStore'])
        ->middleware('permission:notifications.schedules.store,web');
});
