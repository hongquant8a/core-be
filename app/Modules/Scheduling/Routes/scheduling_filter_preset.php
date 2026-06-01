<?php

use App\Modules\Scheduling\Controllers\SchedulingFilterPresetController;
use Illuminate\Support\Facades\Route;

Route::get('/',  [SchedulingFilterPresetController::class, 'index']);
Route::post('/', [SchedulingFilterPresetController::class, 'store']);
Route::put('/{schedulingFilterPreset}', [SchedulingFilterPresetController::class, 'update']);
Route::delete('/{schedulingFilterPreset}',[SchedulingFilterPresetController::class, 'destroy']);
