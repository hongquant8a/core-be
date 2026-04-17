<?php

use App\Modules\Core\NotificationConfigController;
use App\Modules\Core\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/test', [NotificationController::class, 'test'])
    ->middleware('permission:notifications.test,web');

// Event configs
Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
    ->middleware('permission:notifications.event-configs.index,web');
Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
    ->middleware('permission:notifications.event-configs.update,web');

// Schedules
Route::get('/schedules', [NotificationConfigController::class, 'scheduleIndex'])
    ->middleware('permission:notifications.schedules.index,web');
Route::post('/schedules', [NotificationConfigController::class, 'scheduleStore'])
    ->middleware('permission:notifications.schedules.store,web');
Route::put('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleUpdate'])
    ->middleware('permission:notifications.schedules.update,web');
Route::delete('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleDestroy'])
    ->middleware('permission:notifications.schedules.destroy,web');
