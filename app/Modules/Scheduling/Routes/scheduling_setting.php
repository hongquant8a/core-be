<?php

use App\Modules\Scheduling\Controllers\SchedulingSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/',  [SchedulingSettingController::class, 'show']);
Route::post('/', [SchedulingSettingController::class, 'update'])->middleware('permission:scheduling-settings.update,web');
