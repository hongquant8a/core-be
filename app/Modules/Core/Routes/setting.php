<?php

use App\Modules\Core\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/available-channels', [SettingController::class, 'availableChannels']);

Route::get('/', [SettingController::class, 'index'])->middleware('permission:settings.index,web');
Route::get('/{key}', [SettingController::class, 'show'])->middleware('permission:settings.show,web');
Route::match(['put', 'patch'], '/', [SettingController::class, 'update'])->middleware('permission:settings.update,web');
