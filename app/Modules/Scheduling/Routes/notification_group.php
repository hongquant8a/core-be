<?php

use App\Modules\Scheduling\Controllers\NotificationGroupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NotificationGroupController::class, 'index']);
Route::post('/', [NotificationGroupController::class, 'store']);
Route::get('/{notificationGroup}', [NotificationGroupController::class, 'show']);
Route::put('/{notificationGroup}', [NotificationGroupController::class, 'update']);
Route::delete('/{notificationGroup}', [NotificationGroupController::class, 'destroy']);
