<?php

use App\Modules\Meeting\MeetingPersonalNoteController;
use Illuminate\Support\Facades\Route;

Route::post('/bulk-delete', [MeetingPersonalNoteController::class, 'bulkDestroy'])->middleware('permission:meeting-personal-notes.bulkDestroy,web');
Route::patch('/reorder', [MeetingPersonalNoteController::class, 'reorder'])->middleware('permission:meeting-personal-notes.update,web');
Route::get('/', [MeetingPersonalNoteController::class, 'index'])->middleware('permission:meeting-personal-notes.index,web');
Route::get('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'show'])->middleware('permission:meeting-personal-notes.show,web');
Route::post('/', [MeetingPersonalNoteController::class, 'store'])->middleware('permission:meeting-personal-notes.store,web');
Route::put('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'update'])->middleware('permission:meeting-personal-notes.update,web');
Route::patch('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'update'])->middleware('permission:meeting-personal-notes.update,web');
Route::delete('/{meetingPersonalNote}', [MeetingPersonalNoteController::class, 'destroy'])->middleware('permission:meeting-personal-notes.destroy,web');
