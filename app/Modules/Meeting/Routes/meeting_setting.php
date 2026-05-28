<?php

use App\Modules\Meeting\Controllers\MeetingSettingController;
use Illuminate\Support\Facades\Route;

// Singleton per org — GET trả config hiện tại, POST/PUT upsert (multipart).
// GET auth-only (đại biểu cần đọc ảnh màn chiếu / icon QR / signature),
// UPDATE giữ Spatie permission (admin only).
Route::get('/', [MeetingSettingController::class, 'show']);
Route::post('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
Route::put('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
