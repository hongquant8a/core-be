<?php

use App\Modules\Core\SettingController;
use App\Modules\Core\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/available-channels', [SettingController::class, 'availableChannels']);

Route::get('/', [SettingController::class, 'index'])->middleware('permission:settings.index,web');
Route::get('/{key}', [SettingController::class, 'show'])->middleware('permission:settings.show,web');
Route::match(['put', 'patch'], '/', [SettingController::class, 'update'])->middleware('permission:settings.update,web');

// Đăng ký webhook Telegram — thao tác cấu hình, dùng chung quyền settings.update.
// Đặt trước route '/{key}' không vướng vì khác HTTP method.
Route::post('/telegram/webhook', [TelegramController::class, 'registerWebhook'])->middleware('permission:settings.update,web');
