<?php

use App\Modules\Meeting\Controllers\MeetingInvitationTemplateController;
use Illuminate\Support\Facades\Route;

// Cheatsheet biến template — auth-only, không Spatie permission.
Route::get('/variables', [MeetingInvitationTemplateController::class, 'variables']);

// Download file .docx mẫu.
Route::get('/sample', [MeetingInvitationTemplateController::class, 'downloadSample']);

// Export giấy mời .docx từ template — auth-only (sẽ validate & check policy trong controller).
Route::post('/export', [MeetingInvitationTemplateController::class, 'exportInvitation']);

// List template giấy mời phục vụ export (không check Spatie permission admin, chỉ cần auth).
Route::get('/list', [MeetingInvitationTemplateController::class, 'listForExport']);

// CRUD template (admin) — Spatie permission `meeting-invitation-templates.*`.
Route::get('/', [MeetingInvitationTemplateController::class, 'index'])->middleware('permission:meeting-invitation-templates.index,web');
Route::get('/{meetingInvitationTemplate}', [MeetingInvitationTemplateController::class, 'show'])->middleware('permission:meeting-invitation-templates.show,web');
Route::post('/', [MeetingInvitationTemplateController::class, 'store'])->middleware('permission:meeting-invitation-templates.store,web');
Route::post('/{meetingInvitationTemplate}', [MeetingInvitationTemplateController::class, 'update'])->middleware('permission:meeting-invitation-templates.update,web');
Route::put('/{meetingInvitationTemplate}', [MeetingInvitationTemplateController::class, 'update'])->middleware('permission:meeting-invitation-templates.update,web');
Route::delete('/{meetingInvitationTemplate}', [MeetingInvitationTemplateController::class, 'destroy'])->middleware('permission:meeting-invitation-templates.destroy,web');
