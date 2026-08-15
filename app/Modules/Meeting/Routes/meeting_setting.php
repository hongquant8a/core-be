<?php

use App\Modules\Meeting\Controllers\MeetingSettingController;
use Illuminate\Support\Facades\Route;

// Singleton per org — POST/PUT upsert (multipart), giữ Spatie permission (admin only).
// GET đã chuyển sang public (không auth:sanctum) — xem routes/api.php
// prefix /api/public/meeting-settings, guest cần đọc ảnh màn chiếu/QR icon/ảnh chờ.
Route::post('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
Route::put('/', [MeetingSettingController::class, 'update'])->middleware('permission:meeting-settings.update,web');
