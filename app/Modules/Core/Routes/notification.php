<?php

use App\Modules\Core\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/test', [NotificationController::class, 'test'])
    ->middleware('permission:notifications.test,web');
