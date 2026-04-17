<?php

use App\Modules\Core\NotificationConfigController;
use Illuminate\Support\Facades\Route;

/**
 * Routes cấu hình notification cho module TaskAssignment.
 * Schedule nằm dưới event (nested): list/create scoped theo event_key,
 * update/delete theo id (endpoint chung ngoài module).
 */
Route::middleware('notification.module:task_assignment')->group(function () {
    Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
        ->middleware('permission:notifications.event-configs.index,web');
    Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
        ->middleware('permission:notifications.event-configs.update,web');

    Route::get('/event-configs/{eventKey}/schedules', [NotificationConfigController::class, 'scheduleIndex'])
        ->middleware('permission:notifications.schedules.index,web');
    Route::post('/event-configs/{eventKey}/schedules', [NotificationConfigController::class, 'scheduleStore'])
        ->middleware('permission:notifications.schedules.store,web');
});
