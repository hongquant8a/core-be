<?php

use App\Modules\Meeting\MeetingSettingController;
use Illuminate\Support\Facades\Route;

// Singleton per org — GET trả config hiện tại, POST/PUT upsert (multipart).
Route::get('/', [MeetingSettingController::class, 'show'])->middleware('permission:meeting-settings.show,web');
Route::post('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
Route::put('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
