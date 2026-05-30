<?php

use App\Modules\Scheduling\Controllers\OrgSchedulingSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrgSchedulingSettingsController::class, 'show']);
Route::post('/', [OrgSchedulingSettingsController::class, 'update']);
Route::put('/', [OrgSchedulingSettingsController::class, 'update']);
