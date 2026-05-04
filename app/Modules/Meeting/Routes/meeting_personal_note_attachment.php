<?php

use App\Modules\Meeting\MeetingPersonalNoteAttachmentController;
use Illuminate\Support\Facades\Route;

Route::patch('/reorder', [MeetingPersonalNoteAttachmentController::class, 'reorder'])->middleware('permission:meeting-personal-note-attachments.update,web');
Route::get('/', [MeetingPersonalNoteAttachmentController::class, 'index'])->middleware('permission:meeting-personal-note-attachments.index,web');
Route::post('/', [MeetingPersonalNoteAttachmentController::class, 'store'])->middleware('permission:meeting-personal-note-attachments.store,web');
Route::delete('/{meetingPersonalNoteAttachment}', [MeetingPersonalNoteAttachmentController::class, 'destroy'])->middleware('permission:meeting-personal-note-attachments.destroy,web');
