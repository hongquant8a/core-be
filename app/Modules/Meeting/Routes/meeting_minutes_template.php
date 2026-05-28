<?php

use App\Modules\Meeting\Controllers\MeetingMinutesTemplateController;
use Illuminate\Support\Facades\Route;

// Cheatsheet biến template — auth-only, không Spatie permission.
Route::get('/variables', [MeetingMinutesTemplateController::class, 'variables']);

// Download file .docx mẫu chứa toàn bộ placeholder + cấu trúc biên bản HĐND chuẩn.
Route::get('/sample', [MeetingMinutesTemplateController::class, 'downloadSample']);

// CRUD template (admin) — Spatie permission `meeting-minutes-templates.*`.
Route::get('/', [MeetingMinutesTemplateController::class, 'index'])->middleware('permission:meeting-minutes-templates.index,web');
Route::get('/{meetingMinutesTemplate}', [MeetingMinutesTemplateController::class, 'show'])->middleware('permission:meeting-minutes-templates.show,web');
Route::post('/', [MeetingMinutesTemplateController::class, 'store'])->middleware('permission:meeting-minutes-templates.store,web');
Route::post('/{meetingMinutesTemplate}', [MeetingMinutesTemplateController::class, 'update'])->middleware('permission:meeting-minutes-templates.update,web');
Route::put('/{meetingMinutesTemplate}', [MeetingMinutesTemplateController::class, 'update'])->middleware('permission:meeting-minutes-templates.update,web');
Route::delete('/{meetingMinutesTemplate}', [MeetingMinutesTemplateController::class, 'destroy'])->middleware('permission:meeting-minutes-templates.destroy,web');
