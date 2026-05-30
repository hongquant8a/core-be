<?php

use App\Modules\Scheduling\Controllers\ReminderPresetController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReminderPresetController::class, 'index']);
Route::post('/', [ReminderPresetController::class, 'store']);
Route::get('/{reminderPreset}', [ReminderPresetController::class, 'show']);
Route::put('/{reminderPreset}', [ReminderPresetController::class, 'update']);
Route::delete('/{reminderPreset}', [ReminderPresetController::class, 'destroy']);
